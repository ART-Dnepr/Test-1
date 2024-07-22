<?php
	require_once DIR_SYSTEM . 'library/PHPExcel/PHPExcel.php';
	class BasHelper
	{
		
	    var $log_file =  DIR_DOWNLOAD.'bas_import/log.txt';
		var $data_dir =  DIR_DOWNLOAD.'bas_import/data';
		var $entry_prods =  DIR_DOWNLOAD.'bas_import/data/entry';
		var $refs =  DIR_DOWNLOAD.'bas_import/data/refs';
		var $entry_bas_prods =  DIR_DOWNLOAD.'bas_import/data/entry_unt';
		var $upd_dir =  DIR_DOWNLOAD.'bas_import/data/upd';
		var $ins_dir =  DIR_DOWNLOAD.'bas_import/data/ins';
		var $price_list_dir = DIR_DOWNLOAD.'bas_import/prices';
		var $images = DIR_IMAGE.'catalog/products/basimg';
		var $data = array();
		
		public function __construct($registry){
			$this->db = $registry->get('db');
			$this->config = $registry->get('config');
			
		}
		
		function checkDb()
		{
			$analog_table_query = $this->db->query("SELECT * FROM information_schema.tables WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "bas_analogs'");
			if(!$analog_table_query->num_rows)
			{
				$this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."bas_analogs` (
				`pid` int(11) NOT NULL,
				`uid` varchar(45) NOT NULL,
				`vals` text NOT NULL
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
				
				$this->db->query("ALTER TABLE `".DB_PREFIX."bas_analogs`
				ADD PRIMARY KEY (`pid`,`uid`);");
				
			}
			//$this->db->query('ALTER TABLE `' . DB_PREFIX . 'product` ADD `original_codes` TEXT NULL AFTER `date_modified`;');
		}
		
		function checkDir()
		{
			if (!file_exists($this->data_dir)) { 
				mkdir($this->data_dir, 0777, true);
			}
			if (!file_exists($this->upd_dir)) { 
				mkdir($this->upd_dir, 0777, true);
			}
			if (!file_exists($this->ins_dir)) { 
				mkdir($this->ins_dir, 0777, true);
			}
			if (!file_exists($this->price_list_dir)) { 
				mkdir($this->price_list_dir, 0777, true);
			}
			if (!file_exists($this->images)) { 
				mkdir($this->images, 0777, true);
			}
		}
		
		
		function clear()
		{
			$this->checkDir();
			$files = glob($this->data_dir."/*");
			$c = count($files);
			if (count($files) > 0) {
				foreach ($files as $file) {      
					if (file_exists($file)) {
						@unlink($file);
					}   
				}
			}
			unlink($this->entry_prods);
			$files = glob($this->upd_dir."/*");
			$c = count($files);
			if (count($files) > 0) {
				foreach ($files as $file) {      
					if (file_exists($file)) {
						unlink($file);
					}   
				}
			}
			
			$files = glob($this->ins_dir."/*");
			$c = count($files);
			if (count($files) > 0) {
				foreach ($files as $file) {      
					if (file_exists($file)) {
						unlink($file);
					}   
				}
			}
			
			$files = glob($this->price_list_dir."/*");
			$c = count($files);
			if (count($files) > 0) {
				foreach ($files as $file) {      
					if (file_exists($file)) {
						unlink($file);
					}   
				}
			}
		}
		
		public function deleteAll()
		{
		   $pq = $this->db->query("SELECT product_id FROM ".DB_PREFIX."product WHERE uuid LIKE '%bas|%'");
		   foreach($pq->rows as $row)
			{
			    $this->db->query("DELETE FROM " . DB_PREFIX . "product where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_description where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_discount where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_filter where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_image where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_related where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_reward where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_special where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_recurring where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "coupon_product where product_id='".$row['product_id']."'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "review where product_id='".$row['product_id']."'");
			
			$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query LIKE '%product_id=".$row['product_id']."%'");
			}
		}
		
		private function marginPrice($price)
		{
			$margins = $this->config->get('bas_import_api_prices');
			if(isset($margins)&&count($margins)>0)
			{
				foreach($margins as $margin)
				{
					if($price>=$margin['min']&&$price<=$margin['max'])
					{
						$price = $price*(1+($margin['margin']/100));
						break;
					}
				}
			}
			return $price;
		}
		
		
		function getWarehouses() 
		{
			$res = array();
			$res[6] = 'Одесса';
			$res[9] = 'Полтава';
			$res[10] = 'Черкассы';
			$res[11] = 'Днепр';
			$res[12] = 'Львов';
			$res[13] = 'Ивано-Франковск';
			$res[14] = 'Харьков';
			$res[15] = 'Винница';
			$res[16] = 'Тернополь';
			$res[17] = 'Николаев';
			$res[18] = 'Херсон';
			$res[19] = 'Кропивницкий';
			$res[20] = 'Запорожье';
			$res[21] = 'Киев';
			$res[22] = 'Черновцы';
			return $res;
		}
		
		
		function getEntryProds()
		{
			if (!file_exists($this->entry_prods)) {
				$manufacturers = array();
				$mquery = $this->db->query("SELECT * FROM ".DB_PREFIX."manufacturer");
				if($mquery&&$mquery->num_rows>0)
				{
					foreach($mquery->rows as $row)
					{
						$manufacturers[$row['manufacturer_id']] = $row['name'];
					}
				}
				
				$prods = array();
				$pquery = $this->db->query("SELECT product_id,sku,manufacturer_id FROM ".DB_PREFIX."product");
				if($pquery&&$mquery->num_rows>0)
				{
					foreach($pquery->rows as $row)
					{
						$mn = @$manufacturers[$row['manufacturer_id']];
						$prods[mb_strtolower($mn.'_'.$row['sku'])] = $row['product_id'];
						if(isset($mn)&&isset($mn['alt_names'])&&strlen($mn['alt_names'])>0)
						{
					        $altns = explode('|',$mn['alt_names']);
							foreach($altns as $altn)
							{
							    $prods[mb_strtolower($altn.'_'.$row['sku'])] = $row['product_id'];
							}
						}
					}
				}
				
				file_put_contents($this->entry_prods,serialize($prods));
				return $prods;
				}else{
				return unserialize(file_get_contents($this->entry_prods));
			}
			
		}
		
		 
		function updatePrices($data)
		{
		    
			if(count($data)>0)
			{
		        $count = 0;
			
				
				$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE,DB_PORT);
				$entries = $this->getEntryProds();
				foreach($data as $pr){
					
					$pid=0;
					if(isset($entries[mb_strtolower($pr['brand'].'_'.$pr['sku'])]))
					{
				         $pid = $entries[mb_strtolower($pr['brand'].'_'.$pr['sku'])];
					}else if(isset($entries[mb_strtolower($pr['brand'].'_'.$pr['tecdoc'])]))
					{
				        $pid = $entries[mb_strtolower($pr['brand'].'_'.$pr['tecdoc'])];
					}else{
					continue;
					}
					
					if($this->config->get('yr_prices_enable')!=null&&$this->config->get('yr_prices_enable')==1)
					{
						$query.="INSERT INTO `".DB_PREFIX."yr_prices`(`provider`, `price`, `count`, `date`,`product_id`) VALUES ('BAS','".$pr['price']."','".$pr['quantity']."','".date("Y:m:d H:i:s")."','".$pid."');";
					}
					$pr['price'] = $this->marginPrice($pr['price']);
					$query.="UPDATE ".DB_PREFIX."product SET quantity='".$pr['quantity']."',price='".$pr['price']."',stock_status_id=5 WHERE product_id='".$pid."';";
					
					$count++;
					
					
				}
				$mysqli->multi_query($query);
				sleep(2);
				//var_dump($query);
				while (@$mysqli->next_result()) {;}
				//$mysqli->close();
				
				return $count;
			}
			else{
				return false;
			}
		}
		
		
		
		function getNoAnalogs()
		{
		    $pqa = $this->db->query("SELECT pid FROM ".DB_PREFIX."bas_analogs");
			$pqaids = array();
			foreach($pqa->rows as $row)
			{
				$pqaids[] = $row['pid'];
			}
			$query = "SELECT product_id,uuid FROM ".DB_PREFIX."product WHERE uuid LIKE '%bas|%'";
			
			$pq = $this->db->query($query);
			$pids = array();
			$index=0;
			foreach($pq->rows as $row)
			{
			    $row['uuid'] = str_replace('bas|','',$row['uuid']);
				if(!in_array($row['product_id'],$pqaids)){
					$pids[] = $row;
					$index++;
				}
			}
			return $pids;
		}
		
		
		function writeAnalogs($data)
		{
		    $process=0;
			$query = '';
			foreach($data as $it)
			{
			    $query.="INSERT IGNORE INTO `".DB_PREFIX."bas_analogs`(`pid`, `uid`, `vals`) VALUES ('".$it['pid']."','".$it['uid']."','".$this->db->escape($it['vals'])."');";
			    $process++;
				
			}
			//var_dump($query);
			$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE,DB_PORT);
			$mysqli->multi_query($query);
			while (@$mysqli->next_result()) {;}
			return $process;
		}
		
		
        function getEntryBasProds()
		{
			$prods = array();
			$pquery = $this->db->query("SELECT product_id,sku,uuid FROM ".DB_PREFIX."product WHERE uuid LIKE '%bas|%'");
			if($pquery&&$pquery->num_rows>0)
			{
				foreach($pquery->rows as $row)
				{
					$prods[] = array('pid'=>$row['product_id'],'uid'=>str_replace('bas|','',$row['uuid']));
				}
			}
			return $prods;
		}
		
		
		
		function saveTempP($data)
		{
			$file_name = $this->ins_dir.'/parse_'.uniqid();
			file_put_contents($file_name,serialize($data));
			return $file_name;
		}
		
		function saveTemp($data)
		{
			$file_name = $this->ins_dir.'/search_'.uniqid();
			file_put_contents($file_name,serialize($data));
			return $file_name;
		}
		
		function saveTempPr($data)
		{
			$file_name = $this->price_list_dir.'/price_'.uniqid();
			file_put_contents($file_name,serialize($data));
			return $file_name;
		}
		
		function writeApplicability($pid,$cars)
		{
			foreach($cars as $car) 
			{
				$brand = $car['brand'];
				$sql = "SELECT * FROM `oc_auto_brands` WHERE brand_name='".$this->db->escape($brand)."'";
				$brandquery = $this->db->query($sql);
				if ($brandquery->num_rows == 0) {
					//insert new brand setting brand_image as empty like ''
					$this->db->query("INSERT INTO " . DB_PREFIX . "auto_brands SET brand_name = '" . $this->db->escape($car['brand']) . "', brand_img = ''");
					$brand_id = $this->db->getLastId();
				} 
				else {
					$brand_id = $brandquery->row['brand_id'];
				}
				
				$modelstr=$car['modif'];
				$sql = "SELECT model_id, brand_id,model_name FROM `oc_auto_models` WHERE brand_id=$brand_id AND model_name='".$this->db->escape($modelstr)."'";
				$modelQuery= $this->db->query($sql);
				if ($modelQuery->num_rows == 0) {
					//insert new model
					$this->db->query("INSERT INTO " . DB_PREFIX . "auto_models SET brand_id = '" . $brand_id . "', model_name = '" . $this->db->escape($car['modif']) . "'");
					$model_id = $this->db->getLastId();
					} else {
					$model_id = $modelQuery->row['model_id'];
				}
				
				$moddvs = $car['engine'];
				$modname = $modelstr.' '.$car['cuz'].' '.$car['lh'].' л.с.';
				$yearsex = explode('-',$car['year']);
				$start = trim($yearsex[0]);
				$finish = '';
				if(isset($yearsex[1]))
				{
					$finish = trim($yearsex[1]);
				}
				$modifQuery= $this->db->query("SELECT * FROM " . DB_PREFIX . "auto_modifications WHERE model_id = '" . $model_id . "' AND modif_name = '" . $this->db->escape($modname) . "' AND modif_hp = '" . $this->db->escape($car['lh']) . "' AND modif_start = '" . $this->db->escape($start) . "' AND modif_end = '" . $this->db->escape($finish) . "'");
				if ($modifQuery->num_rows == 0) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "auto_modifications SET model_id = '" . $model_id . "', modif_name = '" . $this->db->escape($modname) . "',modif_dvs = '" . $this->db->escape($moddvs) . "',modif_hp = '" . $this->db->escape($car['lh']) . "',modif_start = '" . $this->db->escape($start) . "',modif_end = '" . $this->db->escape($finish) . "',modif_body = '".$this->db->escape($car['cuz'])."'");
					$modif_id = $this->db->getLastId();
					} else {
					$modif_id = $modifQuery->row['modif_id'];
				}
				$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "auto_link_part SET modif_id = '" . $modif_id . "', part_id = '" . $pid . "'");
			}
		}
		
		function insertProduct($data=null,$lang='uk'){
			$uuid = 'bas|'.$data['id'];
			$indb = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE uuid LIKE '".$uuid."'");
			if($indb->num_rows){return $indb->row['product_id'];}
			if(count($data['images'])<1){return null;}
			$this->db->query("INSERT INTO " . DB_PREFIX . "product 
			SET 
			uuid = '" . $this->db->escape('bas|'.$data['id']) . "', 
			model = '" . $this->db->escape($data['model']) . "', 
			sku = '" . $this->db->escape($data['sku']) . "', 
			upc = '', 
			ean = '', 
			jan = '', 
			isbn = '',  
			mpn = '', 
			location = '', 
			quantity = '" . (int)$data['quantity'] . "', 
			minimum = '', 
			subtract = '', 
			stock_status_id = '5', 
			date_available = '0000-00-00', 
			manufacturer_id = '" . (int)$this->getManufacturerId($data['brand']) . "', 
			shipping = '1', 
			price = '" . (float)$this->marginPrice($data['price']) . "', 
			points = '', 
			weight = '0', 
			weight_class_id = '" . $this->config->get('config_weight_class_id') . "', 
			length = '', 
			width = '', 
			height = '', 
			length_class_id = '', 
			status = '1', 
			tax_class_id = '', 
			sort_order = '', 
			date_added = NOW(), 
			date_modified = NOW(),
			original_codes = '".$this->db->escape($data['oe'])."'");
			
			$product_id = $this->db->getLastId();
			
			if (isset($data['images'])) {
				$image = $this->loadImage($data['sku'], $data['images'][0]);
				$this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape($image) . "' WHERE product_id = '" . (int)$product_id . "'");
			}
			
			if (!empty($data['images']) && isset($data['images'][1])) {
				for($i=1;$i<count($data['images']);$i++){
					$sort_order = $i-1;
					if($sort_order>5){break;}//ограничение максимум 6 изображений
					$image = $this->loadImage($data['sku'], $data['images'][$i]);
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' AND image = '" . $this->db->escape($image) . "'");
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_image 
					SET 
					product_id = '" . (int)$product_id . "', 
					image = '" . $this->db->escape($image) . "', 
					sort_order = '" . $sort_order . "'");
					
				}
			}
			
			foreach ($this->getLanguages() as $language_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_description 
				SET 
				product_id = '" . (int)$product_id . "', 
				language_id = '" . (int)$language_id . "', 
				name = '" . $this->db->escape($data['name']) . "', 
				description = '" . $this->db->escape($data['description']) . "', 
				tag = '', 
				meta_title = '', 
				meta_description = '', 
				meta_keyword = ''");
			}
			
			
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");
			
			if (!empty($data['attributes'])) {
				foreach ($data['attributes'] as $product_attribute) {
				    $product_attribute['attribute_id'] = $this->getAttributeId($product_attribute['name'],(int)$this->config->get('bas_import_attribute_group_id'));
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");
					
					foreach ($this->getLanguages() as $language_id) {
						$this->db->query("REPLACE INTO " . DB_PREFIX . "product_attribute 
						SET 
						product_id = '" . (int)$product_id . "', 
						attribute_id = '" . (int)$product_attribute['attribute_id'] . "', 
						language_id = '" . (int)$language_id . "', 
						text = '" .  $this->db->escape($product_attribute['value']) . "'");
					}
				}
			}
			
			
			$data['categories'] = array();
			$data['categories'][] = $this->getCategoryId($data['category'],(int)$this->config->get('bas_import_category_parent_id'));
			if (!empty($data['categories'])) {
				
				foreach ($data['categories'] as $category_id) {
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id = " . (int)$category_id);
					$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
				}
			}
			
			
			
			$data['keyword'] = $this->translit($data['name']);
			
			if (!empty($data['keyword'])) {
				foreach($this->getLanguages() as $language_id) {
					$keyword = $data['keyword'];
					if($language_id == 2){
						$keyword .= '-r';
					} 
					$this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$this->config->get('config_language_id') . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
					
				}
				
			}
			
			
			
			return $product_id;
		}
		
		public function updateAttributes($product_id,$attributes)
		{
			foreach ($attributes as $product_attribute) {
				$product_attribute['attribute_id'] = $this->getAttributeId($product_attribute['name'],(int)$this->config->get('bas_import_attribute_group_id'));
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");
				
				foreach ($this->getLanguages() as $language_id) {
					$this->db->query("REPLACE INTO " . DB_PREFIX . "product_attribute 
					SET 
					product_id = '" . (int)$product_id . "', 
					attribute_id = '" . (int)$product_attribute['attribute_id'] . "', 
					language_id = '" . (int)$language_id . "', 
					text = '" .  $this->db->escape($product_attribute['value']) . "'");
				}
			}
		}
		
		public function updateImages($product_id,$images)
		{
			for($i=1;$i<count($images);$i++){
				$sort_order = $i-1;
				if($sort_order>5){break;}//ограничение максимум 6 изображений
				$image = $this->loadImage($data['sku'], $images[$i]);
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' AND image = '" . $this->db->escape($image) . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_image 
				SET 
				product_id = '" . (int)$product_id . "', 
				image = '" . $this->db->escape($image) . "', 
				sort_order = '" . $sort_order . "'");
				
			}
		}
		
		public function getCategoryId($category_name, $parent_id = 0){
			if(!isset($this->data['categories'])){
				$this->data['categories'] = array();
				$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'category c LEFT JOIN ' . DB_PREFIX . 'category_description cd ON (c.category_id = cd.category_id) WHERE cd.language_id = ' . $this->config->get('config_language_id'));
				foreach($query->rows as $row){
					$this->data['categories'][$row['parent_id']][$row['name']] = $row['category_id'];
				}
			}
			
			if(isset($this->data['categories'][$parent_id][$category_name])){
				return $this->data['categories'][$parent_id][$category_name];
				} else {
				
				
				
				$this->db->query("INSERT INTO " . DB_PREFIX . "category 
				SET 
				parent_id = '" . $parent_id . "', 
				`top` = '1', 
				`column` = '0', 
				sort_order = '0', 
				status = '1', 
				date_modified = NOW(), 
				date_added = NOW()");
				
				$category_id = $this->db->getLastId();
				
				foreach ($this->getLanguages() as $language_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "category_description 
					SET 
					category_id = '" . (int)$category_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($category_name) . "'");
				}
				
				// MySQL Hierarchical Data Closure Table Pattern
				$level = 0;
				
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$parent_id . "' ORDER BY `level` ASC");
				
				foreach ($query->rows as $result) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");
					
					$level++;
				}
				
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");
				
				
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");
				
				foreach ($this->getLanguages() as $language_id) {
					$keyword = $this->translit($category_name);
					if($language_id == 2){
						$keyword .= '-r';
					} 
					
					$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url 
					SET 
					store_id = '" . (int)$this->config->get('config_store_id') . "', 
					language_id = '" . (int)$language_id . "', 
					query = 'category_id=" . (int)$category_id . "', 
					keyword = '" . $this->db->escape($keyword) . "'");
				}
				
				
				$this->data['categories'][$parent_id][$category_name] = $category_id;
				
				return $category_id;
			}
		}
		
		public function getManufacturerId($name){
			if(!isset($this->data['manufacturers'])){
				$this->data['manufacturers'] = array();
				$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'manufacturer');
				foreach($query->rows as $row){
					$this->data['manufacturers'][$row['name']] = $row['manufacturer_id'];
					if(isset($row['alt_names'])&&strlen($row['alt_names'])>0)
					{
						$altns = explode('|',$row['alt_names']);
						foreach($altns as $altn)
						{
							$this->data['manufacturers'][$altn] = $row['manufacturer_id'];
						}
					}
				}
			}
			
			if(isset($this->data['manufacturers'][$name])){
				return $this->data['manufacturers'][$name];
				} else {
				
				$this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer 
				SET 
				name = '" . $this->db->escape($name) . "', 
				sort_order = ''");
				
				$manufacturer_id = $this->db->getLastId();
				
				
				$this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer_to_store SET manufacturer_id = '" . (int)$manufacturer_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");
				
				
				
				foreach ($this->getLanguages() as $language_id) {
					if (!empty($keyword)) {
						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url 
						SET 
						store_id = '" . (int)$this->config->get('config_store_id') . "', 
						language_id = '" . (int)$language_id . "', 
						query = 'manufacturer_id=" . (int)$manufacturer_id . "', 
						keyword = '" . $this->db->escape($this->translit($name)) . "'");
					}
				}
				
				$this->data['manufacturers'][$name] = $manufacturer_id;
				
				return $manufacturer_id;
			}
		}
		
		function getAttributeId($name,$group=0)
		{
			if(!isset($this->data['attributes'])){
				$this->data['attributes'] = array();
				$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'attribute_description');
				foreach($query->rows as $row){
					$this->data['attributes'][$row['name']] = $row['attribute_id'];
				}
			}
			
			if(isset($this->data['attributes'][$name])){
				return $this->data['attributes'][$name];
				} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "attribute 
				SET 
				attribute_group_id = '" . $group . "', 
				sort_order = ''");
				
				$attribute_id = $this->db->getLastId();
				
				foreach ($this->getLanguages() as $language_id) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_description 
					SET 
					attribute_id = '" . (int)$attribute_id . "', 
					language_id = '" . (int)$language_id . "', 
					name = '" . $this->db->escape($name) . "'");
				}
				
				$this->data['attributes'][$name] = $attribute_id;
				
				return $attribute_id;
			}
		}
		
		public function getLanguages(){
			if(!isset($this->data['languages'])){
				$this->data['languages'] = array();
				$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'language');
				foreach($query->rows as $row){
					$this->data['languages'][] = $row['language_id'];
				}
			}
			
			return $this->data['languages'];
		}
		
		public function loadImage($product_code, $image){
			
			$folder = 'catalog/products/basimg';
			
			if(!file_exists(DIR_IMAGE . $folder)){
				mkdir(DIR_IMAGE . $folder, 0777);
			} 
			
			$image_info = pathinfo($image);
			
			$parts = explode('\\', $image_info['basename']);
			
			
			foreach($parts as $part){
				if($part != end($parts)){
					$folder .= $part . '/';
					if(!file_exists(DIR_IMAGE . $folder)){
						mkdir(DIR_IMAGE . $folder, 0777);
					}
				}
			}
			
			
			$image_folder = $folder . '/';          
			$image_name = $image_folder . end($parts);
			
			if(!file_exists(DIR_IMAGE . $image_name)){
				$image = str_replace('\\', '/', $image);
				$headers = get_headers($image);
				if(stripos($headers[0], "200 OK")){
					copy($image, DIR_IMAGE . str_replace('jpeg','jpg',$image_name));
					
					$pos = strpos($image_name, 'jpeg');
					if ($pos === false) {
						
						} else {
						$source =  DIR_IMAGE . str_replace('jpeg','jpg',$image_name);
						$dst_img = DIR_IMAGE . str_replace('jpg','jpeg',$image_name);
						$percent = 0.4;
						$res = (new yr_imgcompress($source,$percent))->compressImg($dst_img);
						unlink($source);
					}
					
				}
			}
			return $image_name;
		}
		
		
	    public function getPriceListData($link)
		{
			$enwarehouses = $this->config->get('bas_import_warehouses');
			$price_file = $this->data_dir.'/price_list.csv';
			file_put_contents($price_file,file_get_contents($link));
			$result = array();
			$row = 1;
			if (($handle = fopen($price_file, "r")) !== FALSE) {
				while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
					$num = count($data);
					if($num==24&&$data[1]!="Наименование")
					{
						$pr = array(
						'name'=>trim(str_replace('ы','и',str_replace('a_','',str_replace('а_','',$data[0])))),
						'brand'=>str_replace('Ö','O',$data[1]),
						'sku'=>trim(str_replace('"','',$data[2]),'='),
						'tecdoc'=>trim(str_replace('"','',$data[3]),'='),
						'rprice'=>$data[4],
						'price'=>$data[5],
						'allcount'=>$data[7],
						'quantity'=>0
						);
						$chr_ru = "А-Яа-яІіЇї\s";
						
						$nmexp = explode(' ',$pr['name']);
						$ctn='';
						//$flag=true;
						foreach($nmexp as $nme)
						{
							if (preg_match("/^[$chr_ru]+$/u", $nme)) {
								$ctn.=$nme.' ';
								break;
							}else{break;}
						}
						$pr['category'] = str_replace("РМК",'РЕМКОМПЛЕКТ',trim($ctn));
						if(strlen($pr['category'])==0){continue;}
						
						foreach($this->getWarehouses() as $key=>$val)
						{
							if(isset($data[$key])&&is_numeric($data[$key])&&in_array($val,$enwarehouses))
							{
								//$pr[$val]=$data[$key];
								$pr['quantity']+=$data[$key];
							}
						}
						$result[mb_strtoupper($pr['category'])][] = $pr;
						//break;
					}
					$row++;
				}
				fclose($handle);
			}
			
			foreach($result as $key=>$val)
			{
				$this->saveTempPr(array('category'=>$key,'products'=>$val));
			}
			
			return $result;
		}
		
		public function translit($name) {
			return utf8_strtolower($this->toAscii(trim(html_entity_decode($name))));
		}
		
		public function toAscii($string){
			// cz
			$source[] = '/а/'; $replace[] = 'a';
			$source[] = '/б/'; $replace[] = 'b';
			$source[] = '/в/'; $replace[] = 'v';
			$source[] = '/г/'; $replace[] = 'g';
			$source[] = '/д/'; $replace[] = 'd';
			$source[] = '/е/'; $replace[] = 'e';
			$source[] = '/ё/'; $replace[] = 'je';
			$source[] = '/ж/'; $replace[] = 'zh';
			$source[] = '/з/'; $replace[] = 'z';
			$source[] = '/и/'; $replace[] = 'y';
			$source[] = '/і/'; $replace[] = 'i';
			$source[] = '/й/'; $replace[] = 'i';
			$source[] = '/к/'; $replace[] = 'k';
			$source[] = '/л/'; $replace[] = 'l';
			$source[] = '/м/'; $replace[] = 'm';
			$source[] = '/н/'; $replace[] = 'n';
			$source[] = '/о/'; $replace[] = 'o';
			$source[] = '/п/'; $replace[] = 'p';
			$source[] = '/р/'; $replace[] = 'r';
			$source[] = '/с/'; $replace[] = 's';
			$source[] = '/т/'; $replace[] = 't';
			$source[] = '/у/'; $replace[] = 'u';
			$source[] = '/ф/'; $replace[] = 'f';
			$source[] = '/х/'; $replace[] = 'h';
			$source[] = '/ц/'; $replace[] = 'c';
			$source[] = '/ч/'; $replace[] = 'ch';
			$source[] = '/ш/'; $replace[] = 'sch';
			$source[] = '/щ/'; $replace[] = 'tsch';
			$source[] = '/ъ/'; $replace[] = '';
			$source[] = '/ы/'; $replace[] = 'y';
			$source[] = '/ь/'; $replace[] = '';
			$source[] = '/э/'; $replace[] = 'e';
			$source[] = '/ю/'; $replace[] = 'yu';
			$source[] = '/я/'; $replace[] = 'ya';
			
			
			
			// CZ
			$source[] = '/А/'; $replace[] = 'a';
			$source[] = '/Б/'; $replace[] = 'b';
			$source[] = '/В/'; $replace[] = 'v';
			$source[] = '/Г/'; $replace[] = 'g';
			$source[] = '/Д/'; $replace[] = 'd';
			$source[] = '/Е/'; $replace[] = 'e';
			$source[] = '/Ё/'; $replace[] = 'je';
			$source[] = '/Ж/'; $replace[] = 'zh';
			$source[] = '/З/'; $replace[] = 'Z';
			$source[] = '/І/'; $replace[] = 'i';
			$source[] = '/И/'; $replace[] = 'y';
			$source[] = '/Й/'; $replace[] = 'j';
			$source[] = '/К/'; $replace[] = 'k';
			$source[] = '/Л/'; $replace[] = 'l';
			$source[] = '/М/'; $replace[] = 'm';
			$source[] = '/Н/'; $replace[] = 'n';
			$source[] = '/О/'; $replace[] = 'o';
			$source[] = '/П/'; $replace[] = 'p';
			$source[] = '/Р/'; $replace[] = 'r';
			$source[] = '/С/'; $replace[] = 's';
			$source[] = '/Т/'; $replace[] = 't';
			$source[] = '/У/'; $replace[] = 'u';
			$source[] = '/Ф/'; $replace[] = 'f';
			$source[] = '/Х/'; $replace[] = 'h';
			$source[] = '/Ц/'; $replace[] = 'c';
			$source[] = '/Ч/'; $replace[] = 'ch';
			$source[] = '/Ш/'; $replace[] = 'sch';
			$source[] = '/Щ/'; $replace[] = 'tsch';
			$source[] = '/Ъ/'; $replace[] = '';
			$source[] = '/Ы/'; $replace[] = 'y';
			$source[] = '/Ь/'; $replace[] = '';
			$source[] = '/Э/'; $replace[] = 'e';
			$source[] = '/Ю/'; $replace[] = 'yu';
			$source[] = '/Я/'; $replace[] = 'ya';
			
			
			$string = preg_replace($source, $replace, $string);
			
			for ($i=0; $i<strlen($string); $i++)
			{
				if ($string[$i] >= 'a' && $string[$i] <= 'z') continue;
				if ($string[$i] >= 'A' && $string[$i] <= 'Z') continue;
				if ($string[$i] >= '0' && $string[$i] <= '9') continue;
				$string[$i] = '-';
			}
			$string = str_replace("--","-",$string);
			$string = trim($string, '-');
			return $string;
		}
	}
	
	class yr_imgcompress{
		
		private $src;
		private $image;
		private $imageinfo;
		private $percent = 0.5;
		
		/**
			* Сжатие изображения
			* @param $ src исходное изображение
			* @param float $ процент сжатия
		*/
		public function __construct($src, $percent=1)
		{
			$this->src = $src;
			$this->percent = $percent;
		}
		
		
		
		public function compressImg($saveName='')
		{
			$this->_openImage();
			if (! empty ($saveName)) $this->_saveImage($saveName); // Сохраняем
			else $this->_showImage();
		}
		
		/**
			* Внутренний: открыть изображение
		*/
		private function _openImage()
		{
			list($width, $height, $type, $attr) = getimagesize($this->src);
			$this->imageinfo = array(
			'width'=>$width,
			'height'=>$height,
			'type'=>image_type_to_extension($type,false),
			'attr'=>$attr
			);
			if(isset($this->imageinfo['type'])){
				
				$fun = "imagecreatefrom".$this->imageinfo['type'];
				if($fun=="imagecreatefromjpeg"||$fun=="imagecreatefrompng"||$fun=="imagecreatefromgif"){
					$this->image = $fun($this->src);
					$this->_thumpImage();
				}
			}
		}
		/**
			* Внутренний: рабочие изображения
		*/
		private function _thumpImage()
		{
			$new_width = $this->imageinfo['width'] * $this->percent;
			$new_height = $this->imageinfo['height'] * $this->percent;
			$image_thump = imagecreatetruecolor($new_width,$new_height);
			// Копируем исходное изображение с носителя изображения и сжимаем его до определенного соотношения, что значительно сохраняет четкость
			imagecopyresampled($image_thump,$this->image,0,0,0,0,$new_width,$new_height,$this->imageinfo['width'],$this->imageinfo['height']);
			imagedestroy($this->image);
			$this->image = $image_thump;
		}
		/**
			* Изображение вывода: сохраните изображение с помощью saveImage ()
		*/
		private function _showImage()
		{
			header('Content-Type: image/'.$this->imageinfo['type']);
			$funcs = "image".$this->imageinfo['type'];
			$funcs($this->image);
		}
		/**
			* Сохраните картинку на жесткий диск:
			* @param string $ dstImgName 1. Вы можете указать имя строки без суффикса и использовать расширение исходного изображения. 2. Непосредственно укажите имя целевого изображения с расширением.
		*/
		private function _saveImage($dstImgName)
		{
			if(empty($dstImgName)) return false;
			$allowImgs = ['.jpg', '.jpeg', '.png', '.bmp', '.wbmp', '.gif']; // Если имя целевого изображения имеет суффикс, используйте суффикс расширения целевого изображения , Если нет, используйте расширение исходного изображения
			$dstExt =  strrchr($dstImgName ,".");
			$sourseExt = strrchr($this->src ,".");
			if(!empty($dstExt)) $dstExt =strtolower($dstExt);
			if(!empty($sourseExt)) $sourseExt =strtolower($sourseExt);
			
			// Есть указанное расширение имени цели
			if(!empty($dstExt) && in_array($dstExt,$allowImgs)){
				$dstName = $dstImgName;
				}elseif(!empty($sourseExt) && in_array($sourseExt,$allowImgs)){
				$dstName = $dstImgName.$sourseExt;
				}else{
				$dstName = $dstImgName.$this->imageinfo['type'];
			}
			$funcs = "image".$this->imageinfo['type'];
			if($funcs=="imagejpeg"||$funcs=="imagepng"||$funcs=="imagemgif"){
				$funcs($this->image,$dstName);
			}
		}
		
		/**
			* Уничтожить картинку
		*/
		public function __destruct(){
			imagedestroy($this->image);
		}
		
	}
