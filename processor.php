<?php
defined('IN_IA') or exit('Access Denied');
define('TIME', '300');
define('SERVERURL', 'http://bus.pouaka.com');
class Vittor_busModuleProcessor extends WeModuleProcessor {
	/*
	通过post请求发送json
	@data 数据
	*/
	public static function postRequest($data='') {
		$ch = curl_init(SERVERURL); 
		if(is_array($data)){
			$postdata = http_build_query($data);
		}else{
			$postdata=$data;
		}
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ch, CURLOPT_POST, 1);  
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");   
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: charset=utf-8'));   
		$result=curl_exec($ch);  
		curl_close($ch);
		return $result;
	}
	//获取bdkey
	public function getBdkey($type=0){
		global $_W;
		if(empty($type)){
			//查询bdkey
			$bdkey_info = pdo_fetchall("SELECT bdkey FROM ".tablename('bus_info')." WHERE weid=:weid",array('weid'=>$_W['weid']));
			//如果为空
			if(empty($bdkey_info)){
				$bdkey=0;
			}else{
				//赋值
				$bdkey=$bdkey_info[0]['bdkey'];
			}
			return $bdkey;
		}else{
			//查询bdkey
			$info = pdo_fetchall("SELECT bdkey,map_amap_server_key,map_amap_web_key,map_baidu_server_key FROM ".tablename('bus_info')." WHERE weid=:weid",array('weid'=>$_W['weid']));
			return $info[0];
		}
	}
	//接受消息自动回复
	public function respond() {
		global $_W;
		//查询bdkey
		$info=$this->getBdkey(1);
		$bdkey=$info['bdkey'];
		//如果为空
		if(empty($bdkey)){
			return $this->respText('服务未接入!');
		}
		//获取文字信息
		$content = $this->message['content'];
		//判断是否为上下文
		if (!($this->inContext)){
				//查询班车
				if(preg_match("/^班车查询$/",$content)){
					return $this->respText($this->query_binding($bdkey,$info['map_baidu_server_key']));
				//更换班车
				}else if(preg_match("/^班车说明$/",$content)){
					//触发上下文
					$_SESSION['find_type']=2;
					//上下文时间20s
					$this->beginContext(60);
					return $this->respText("查询班车："."\n".
											"直接点击“查询班车”"."\n".
											"更换班车："."\n".
											"1.点击“更换班车”"."\n".
											"2.填写班车序号（数字）");
				}else if(preg_match("/^\#vittor_bus/",$content)){
					$content_arr = explode("#",$content);
					$content_arr = explode("@",$content_arr['1']);
					if($content_arr[1]==='add_admin'){
						//发送请求
						$html=$this->postRequest(array(
													'type'=>'addadmin',
													'bdkey'=>$bdkey,
													'manage'=>1,
													'gzh_openid'=>$_W['openid'],
													'is_check'=>'1',
													'add_admin_key'=>$content_arr[2]
													));
						//解析请求
						$add_admin_info_arr=json_decode($html,true);
						//判断是否成功
						if($add_admin_info_arr['state']=='ok'){
							return $this->respText($add_admin_info_arr['text']);
						}else{
							return $this->respText($add_admin_info_arr['text']);
						}
					}
				}else{
					return $this->respText($this->choose_route($bdkey));
				}
		//更换班车
		}else{
			//申请管理员入口
			if($_SESSION['find_type']===2){
				if($content=='0'){
					$convert_url = SERVERURL."?type=isadmin&bdkey=".$bdkey."&gzh_openid=".$_W['openid'];
					$result = file_get_contents($convert_url);
					$temp = json_decode($result, true);
					$dev_list=$temp['dev_list'];
					if($temp['state']=='ok'){
						//关闭上下文
						$this->endContext();
						return $this->respNews(array(
													'Title' => '车辆管理系统',
													'Description' => '点击进入车辆管理系统',
													'Url' => $get_map_url=$this->createMobileUrl('GetLocationsLog'),
													));
					}
				}
			}else{
				return $this->respText($this->user_binding($bdkey,$info['map_baidu_server_key']));
			}
		}
	}
	/*
	函数名：query_binding
	函数功能描述：查询绑定的车辆信息
	函数参数：	int		bdkey	当前公众号的唯一识别ID
	函数返回值：String	企业/班车列表/其他信息
	*/
	public function query_binding($bdkey,$map_baidu_server_key){
		global $_W;
		//发送查询请求
		$html=$this->postRequest(array(
									'type'=>'userquerybus',
									'bdkey'=>$bdkey,
									'gzh_openid'=>$_W['openid'],
								));
		$demodulation=json_decode($html,true);
		//获取成功
		if($demodulation['state']=='ok'){
			return($this->bus_location_imformation($demodulation['bus_info']['longitude'],$demodulation['bus_info']['latitude'],$demodulation['bus_info']['location_time'],$demodulation['bus_info']['company_name'],$demodulation['bus_info']['route'],$demodulation['bus_info']['plate_num'],$map_baidu_server_key));
		//未绑定
		}else if($demodulation['state']=='no_binding'){
			return($this->choose_route($bdkey));
		//其他状态
		}else{
			return($demodulation['text']);
		}
	}
	/*
	函数名：choose_route
	函数功能描述：返回班车列表
	函数参数：	int		bdkey	当前公众号的唯一识别ID
				int		type	查询类型	0/1		企业/班车
				int		error	序号是否错误	0/1		正常/错误
	函数返回值：String	企业/班车列表/其他信息
	*/
	public function choose_route($bdkey,$type=0,$error=0){
		global $_W;
		//返回企业列表
		if($type===0){
			//发送查询请求
			$html=$this->postRequest(array(
										'type'=>'querycompanylist',
										'bdkey'=>$bdkey,
										));
			//解析字符串
			$company_arr=json_decode($html,true);
			//获取信息是否正确
			if($company_arr['state']!=='ok'){
				//返回失败信息
				return ($company_arr['text']);
			}
			//判断返回列表类型
			//企业列表
			if($company_arr['type']==='company_list'){
				$company_count = count($company_arr['company_list']);
				//拼接企业信息字符串
				$company_information = $company_arr['company_list'][0]['id'].". ".$company_arr['company_list'][0]['name']."\n";
				for($i = 1; $i < $company_count; $i++){
					//字符串拼接
					$company_information = $company_information.$company_arr['company_list'][$i]['id'].". ".$company_arr['company_list'][$i]['name']."\n";
				}
					//字符串拼接
					$company_information = 	'<a target="_blank" href="http://wxref=mp.weixin.qq.com#wechat_redirect">请回复自己需要查询企业的序号</a>'.
											"\n-----------------------\n".
											$company_information.
											"-----------------------\n".
											"平台所支持的企业列表";
				if($error === 1){
					$company_information = "输入的企业序号有误，请重新输入!\n".$company_information;
				}
				//查询类型
				$_SESSION['find_type'] = 0;
				//列表数量
				$_SESSION['num_count'] = $company_count;
				//触发上下文
				$this->beginContext(TIME);
				//返回班车列表信息
				return ($company_information);
			//班车列表
			}else if($company_arr['type']==='bus_list'){
				//班车列表
				$type = 1 ;
				$bus_arr=$company_arr;
				//保存企业ID
				$_SESSION['company_id']=$bus_arr['company_id'];
				//触发上下文
				$this->beginContext(TIME);
			}
		}
		//返回班车列表
		if($type===1){
			if(!isset($bus_arr)){
				//发送查询请求
				$html=$this->postRequest(array(
											'type'=>'querybuslist',
											'bdkey'=>$bdkey,
											'company_id'=>$_SESSION['company_id'],
											));
				//解析字符串
				$bus_arr=json_decode($html,true);
				//判断获取班车列表信息是否成功
				if($bus_arr['state']!=='ok'){
					//返回失败信息
					return ($bus_arr['text']);
				}
			}
			$bus_count = count($bus_arr['bus_list']);
			//字符串拼接
			$routes_information = $bus_arr['bus_list'][0]['route_num'].". ".$bus_arr['bus_list'][0]['route']."\n";
			for($i = 1; $i < $bus_count; $i++){
				//字符串拼接
				$routes_information = $routes_information.$bus_arr['bus_list'][$i]['route_num'].". ".$bus_arr['bus_list'][$i]['route']."\n";
			}
			//字符串拼接
			$routes_information = 	'<a target="_blank" href="http://wxref=mp.weixin.qq.com#wechat_redirect">请回复自己需要乘坐班车的序号</a>'.
									"\n-----------------------\n".
									$routes_information.
									"-----------------------\n".
									$bus_arr['company_name']."班车列表";
			if($error===1){
				$routes_information="输入的班车序号有误，请重新输入!\n".$routes_information;
			}
			$_SESSION['find_type'] = 1;
			//列表数量
			$_SESSION['num_count'] = $bus_count;
			//返回班车列表信息
			return ($routes_information);
		}
	}
	
	/*
	函数名：user_binding_route
	函数功能描述：用户与企业或路线绑定
	函数参数：	int		bdkey	当前公众号的唯一识别ID
	函数返回值：String	绑定信息
	*/
	public function user_binding($bdkey,$map_baidu_server_key){
		global $_W;
		//获取用户输入的信息
		$content = $this->message['content'];
		//判断用户输入的是否为数字
		if(is_numeric($content)) {
			$content=abs(intval($content));
			//查询类型
			$find_type = $_SESSION['find_type'];
			if($find_type === 0){
				$_SESSION['company_id']=$content;
				//查询班车信息
				return $this->choose_route($bdkey,1,0);
			}
			if($find_type === 1){
				//发送查询请求
				$html=$this->postRequest(array(
											'type'=>'changebinding',
											'bdkey'=>$bdkey,
											'gzh_openid'=>$_W['openid'],
											'company_id'=>$_SESSION['company_id'],
											'route_num'=>$content,
											));
				//解析字符串
				$company_arr=json_decode($html,true);
				//判断绑定是否成功
				if($company_arr['state']!='ok'){
					return($company_arr['text']);
				}
				//关闭上下文
				$this->endContext();
				//查询绑定后的信息
				return($this->query_binding($bdkey,$map_baidu_server_key));
			}
		}
		//如果绑定失败则返回重新绑定的信息
		return "请回复数字!";
	}
	
	/*
	函数名：bus_location_imformation
	函数功能描述：查询坐标所在位置的相关信息
	函数参数：	String	bus_lng 		纬度
				String	bus_lat			经度
				String	bus_time		位置更新时间
				String	bus_company		企业信息
				String	bus_route		班车路线
				String	bus_plate_num	班车车牌号码
	函数返回值：String	车辆位置信息
	*/
	public function bus_location_imformation($bus_lng,$bus_lat,$bus_time,$bus_company,$bus_route,$bus_plate_num,$map_baidu_server_key){
		global $_W;
		//获取设备纬度
		$longitude = $bus_lng;
		//获取设备经度
		$latitude = $bus_lat;
		//判断设备是否已经上传了位置信息
		if(strlen($longitude)>0 and strlen($latitude)>0 and $longitude!=='0' and $latitude!=='0' ){
			//获取当前公众号的唯一识别码
			$weid = $_W['weid'];
			//获取设备坐标更新时间
			$gps_imformation_time = $bus_time;
			//获取设备坐标更新时间距离当前时间的时间间隔
			$gps_imformation_update_space = round((TIMESTAMP - $gps_imformation_time)/60);
			//获取用户绑定的路线
			$get_user_route_num = $user_route_num;
			//将GPS定位坐标转换为百度坐标
			$url ="http://api.map.baidu.com/geoconv/v1/?coords=".$longitude.",".$latitude."&ak=".$map_baidu_server_key; 
			$results = ihttp_request($url);
			//将获取到的信息进行转码，转换为数组
			$results['content'] = json_decode($results['content'], true);
			//获取转换后的纬度
			$longitude = $results['content']['result'][0]['x'];
			//获取转换后的经度
			$latitude = $results['content']['result'][0]['y'];
			//获取当前班车所在位置信息（百度地图）
			$url = "http://api.map.baidu.com/geocoder/v2/?ak=".$map_baidu_server_key."&location=".$latitude.",".$longitude."&output=json&pois=1";
			$results = ihttp_request($url);
			//将获取到的信息进行转码，转换为数组
			$results['content'] = json_decode($results['content'], true);
			//城市信息
			$city = $result['content']['result']['addressComponent']['city'];
			//城区信息
			$district = $results['content']['result']['addressComponent']['district'];
			//街道信息
			$street = $results['content']['result']['addressComponent']['street'];
			//其他周边信息
			$pois = $results['content']['result']['pois'];
			//获取地图的地址
			
			$get_map_url=$this->createMobileUrl('GetMap')."&state=0";
			$bus_location_imformation = $bus_company."\n".
										$bus_route."\n".
										$bus_plate_num.
										"\n--------------------\n".
										'<a href="'.$get_map_url.'">班车当前位置为:</a>'."\n".
										$city.$district.$street."\n".
										$pois[0]['name']."附近".
										"\n--------------------\n".
										"班车位置信息更新时间:\n".
										'<a href="'.$get_map_url.'">'.date("Y-m-d H:i:s", $gps_imformation_time).'</a>'.
										"\n--------------------\n".
										'以上信息仅供班车查询参考'."\n".
										'小提示：点击蓝色文字可以通过地图查看当前班车的位置噢！';
			//如果备坐标更新时间距离当前时间的时间间隔大于60分钟则为掉线状态
			if((TIMESTAMP - $gps_imformation_time) > 60) {
				$bus_location_imformation = 
											'<a href="'.$get_map_url.'">'.
											'请注意查看设备信息的更新时间，以免错过班车！！！</a>'.
											"\n--------------------\n".
											$bus_location_imformation;
			}
		}else{
			$bus_location_imformation="班车还未上传位置信息，请等候！";
		}
		return $bus_location_imformation;
	}
	
	
	
	
	
	
	
	
	
	
	
	/*
	函数名：bus_location_imformation_img
	函数功能描述：查询坐标所在的位置的图片信息的链接
	函数参数：	String	bus_lng 		经度
				String	bus_lat			纬度
				String	bus_time		位置更新时间
	函数返回值：String	'offline'		设备离线
				String	'http://*****'	图片链接
	*/
	public function bus_location_imformation_img($bus_lng,$bus_lat,$bus_time,$map_baidu_server_key){
		global $_W;
		//获取设备坐标更新时间
		$gps_imformation_time = $bus_time;
		//获取设备坐标更新时间距离当前时间的时间间隔
		$gps_imformation_update_space = round((TIMESTAMP - $gps_imformation_time)/60);
		//如果备坐标更新时间距离当前时间的时间间隔大于2分钟则为掉线状态
		if($gps_imformation_update_space>=2){
			$return_information = "offline";
		}else{
			//获取当前公众号的唯一识别码
			$weid = $_W['weid'];
			//获取设备经度
			$longitude = $bus_lng;
			//获取设备纬度
			$latitude = $bus_lat;
			//将GPS定位坐标转换为百度坐标
			$url ="http://api.map.baidu.com/geoconv/v1/?coords=".$longitude.",".$latitude."&ak=".$map_baidu_server_key; 
			$results = ihttp_request($url);
			//将获取到的数据转码为数组格式
			$results['content'] = json_decode($results['content'], true);
			//获取转换后的经度
			$longitude = $results['content']['result'][0]['x'];
			//获取转换后的纬度
			$latitude = $results['content']['result'][0]['y'];
			//字符串拼接，拼接为图片链接
			$return_information = "http://api.map.baidu.com/staticimage/v2?ak=".$map_baidu_server_key."&center=".$longitude .",".$latitude."&markers=车辆位置|".$longitude .",".$latitude."&width=500&height=350&zoom=17,http://api.map.baidu.com/images/marker_red.png,-1,23,25";
		}
		//函数返回值
		return $return_information;
  	}
 
	/*
	函数名：school_bus_location_imformation_img
	函数功能描述：查询坐标所在的位置的图片信息的链接
	函数参数：	String	bus_lng 		经度
				String	bus_lat			纬度
				String	bus_time		位置更新时间
	函数返回值：String	'offline'		设备离线
				String	'http://*****'	图片链接
	*/
	public function school_bus_location_imformation_img($bus_lng,$bus_lat,$bus_time,$map_baidu_server_key){
		global $_W;
		//获取小黄车所在位置的图片链接
		$img_url = $this->bus_location_imformation_img($bus_lng,$bus_lat,$bus_time,$map_baidu_server_key);
		if($img_url === "offline"){
			return $this->respText("诶妈，累死我了，我得趴会窝充会电，你看看我另外一个兄弟在那，让他来接你~~" );
		}else{
			return $this->respNews(array(
										'Title' => '点击查看车辆位置',
										'Description' => '点击查看车辆位置',
										'PicUrl' => $img_url,
										'Url' => $get_map_url=$this->createMobileUrl('GetMap')."&longitude=".$bus_lng."&latitude=".$bus_lat,
										)
									);
		}
	}
}

