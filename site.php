<?php
defined('IN_IA') or exit('Access Denied');
define('SERVERURL', 'http://bus.pouaka.com');
class Vittor_busModuleSite extends WeModuleSite {
	/*
	通过post请求发送json
	@data 数据
	*/
	public static function postRequest($data='',$content_type='text/plain') {
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
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:'.$content_type.';charset=utf-8'));   
		$result=curl_exec($ch);  
		curl_close($ch);
		return $result;
	}
	/*
	将数组转换为json格式,并且支持中文
	*/
	public function encode($value){
		if (version_compare(PHP_VERSION,'5.4.0','<')){
			$str = json_encode($value);
			$str = preg_replace_callback(
										"#\\\u([0-9a-f]{4})#i",
										function($matchs){
											 return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
										},
										 $str
										);
			return $str;
		}else{
			return json_encode($value, JSON_UNESCAPED_UNICODE);
		}
	}
	//获取bdkey
	public function getBdkey($type=0){
		global $_GPC,$_W;
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
	//班车信息管理页
	public function doWebManager(){
		global $_GPC,$_W;
		//查询bdkey
		$bdkey=$this->getBdkey();
		//判断bdkey是否为空
		if(empty($bdkey)){
			$this->doWebAccessManager();
		}else{
			if(!empty($_GPC['type'])){
				if($_GPC['type']=='addbus'){
					if(!empty($_GPC['company-id']) and !empty($_GPC['imei']) and isset($_GPC['route-num']) and isset($_GPC['state'])){
						//生成要发送的数据
						$data=array('type'=>'addbus','manage'=>1,'bdkey'=>$bdkey,'company_id'=>$_GPC['company-id'],'imei'=>$_GPC['imei'],'route_num'=>$_GPC['route-num'],'state'=>$_GPC['state']);
						if(isset($_GPC['route'])){
							$data['route']=$_GPC['route'];
						}
						if(isset($_GPC['plate-num'])){
							$data['plate_num']=$_GPC['plate-num'];
						}
						if(isset($_GPC['phone-num'])){
							$data['phone_num']=$_GPC['phone-num'];
						}
						$add_bus_info=$this->postRequest($data);
						$add_bus_info_arr=json_decode($add_bus_info,true);
						if($add_bus_info_arr['state']==='ok'){
							$state='1';
							$text='添加成功！';
						}else{
							message($add_bus_info_arr['text']);
						}
					}else{
						message('参数错误！');
					}
				}else if($_GPC['type']=='changebus'){
					if(!empty($_GPC['company-id']) and !empty($_GPC['imei']) and isset($_GPC['route-num']) and isset($_GPC['state'])){
						//生成要发送的数据
						$data=array('type'=>'changebus','manage'=>1,'bdkey'=>$bdkey,'now_company_id'=>$_GPC['now-company-id'],'imei'=>$_GPC['imei'],'route_num'=>$_GPC['route-num'],'state'=>$_GPC['state']);
						if(isset($_GPC['company-id'])){
							$data['company_id']=$_GPC['company-id'];
						}
						if(isset($_GPC['route'])){
							$data['route']=$_GPC['route'];
						}
						if(isset($_GPC['plate-num'])){
							$data['plate_num']=$_GPC['plate-num'];
						}
						if(isset($_GPC['phone-num'])){
							$data['phone_num']=$_GPC['phone-num'];
						}
						$add_bus_info=$this->postRequest($data);
						$add_bus_info_arr=json_decode($add_bus_info,true);
						if($add_bus_info_arr['state']==='ok'){
							$state='1';
							$text='修改成功！';
							$_GPC['company-id']=$_GPC['now-company-id'];
						}else{
							message($add_bus_info_arr['text']);
						}
					}else{
						message('参数错误！');
					}
				}
				if(!empty($_GPC['company-id']) and isset($_GPC['company-name'])){
					$company_name=$_GPC['company-name'];
					$company_id=$_GPC['company-id'];
					$bus_info=$this->postRequest(array('type'=>'querybus','manage'=>1,'bdkey'=>$bdkey,'company_id'=>$_GPC['company-id']));
					$bus_info_arr=json_decode($bus_info,true);
					if($bus_info_arr['state']!=='ok'){
						$state='0';
						$text=$bus_info_arr['text'];
					}
				}else{
					message('参数错误！');
				}
			}else{
				$state='0';
				$text='请选择企业\分组！';
			}
			$request=$this->postRequest(array('type'=>'querycompany' , 'manage'=>'1' , 'bdkey'=>$bdkey));
			$request_arr=json_decode($request,true);
			//判断请求是否成功
			if($request_arr['state']!=='ok'){
				$text=$request_arr['text'];
			}else{
				if(!isset($state)){
					$state='1';
					$text='查询成功！';
				}
			}
			include $this->template('bus-manager');
		}
	}
	//接入信息管理页
	public function doWebAccessManager(){
		global $_GPC,$_W;
		//判断是否为POST请求
		if($_W['ispost']){
			if(($_GPC['type']==='getbdkey' or $_GPC['type']==='servicesignup') and (empty($_GPC['bdcode']) or empty($_GPC['bdpwd']))){
				message("账号和密码不能为空!");
			}else if(($_GPC['type']==='getbdkey' or $_GPC['type']==='servicesignup') and strlen($_GPC['bdcode'])<6){
				message("账号不可以小于6位!");
				message("主体代码和主体密码不能为空!");
			}else if(($_GPC['type']==='getbdkey' or $_GPC['type']==='servicesignup') and strlen($_GPC['bdcode'])<6){
				message("主体代码不可以小于6位!");
			}else if(($_GPC['type']==='getbdkey' or $_GPC['type']==='servicesignup') and strlen($_GPC['bdpwd'])<6){
				message("密码不可以小于6位!");
			}else{
				if($_GPC['type']==='getbdkey'){
					$request=$this->postRequest(array('type'=>'getbdkey' , 'nobdkey'=>'1' , 'bdcode'=>$_GPC['bdcode'] , 'bdpwd'=>$_GPC['bdpwd']));
					$request_arr=json_decode($request,true);
					if($request_arr['state']==='ok'){
						$bdkey=$request_arr['bdkey'];
						pdo_insert('bus_info', $data = array('weid'=>$_W['weid'] , 'bdkey'=>$bdkey), true);
						$state='1';
						$text='接入成功!';
					}else{
						message($request_arr['text']);
					}
				}else if($_GPC['type']==='servicesignup'){
					$data=array('type'=>'servicesignup' , 'nobdkey'=>'1' , 'bdcode'=>$_GPC['bdcode'] , 'bdpwd'=>$_GPC['bdpwd']);
					if(!empty($_GPC['bdcode'])){
						$data['phone_num']=$_GPC['phone_num'];
					}
					if(!empty($_GPC['bdcode'])){
						$data['email']=$_GPC['email'];
					}
					if(!empty($_GPC['bdcode'])){
						$data['qq']=$_GPC['qq'];
					}
					$request=$this->postRequest($data);
					$request_arr=json_decode($request,true);
					if($request_arr['state']==='ok'){
						$bdkey=$request_arr['bdkey'];
						pdo_insert('bus_info', $data = array('weid'=>$_W['weid'] , 'bdkey'=>$bdkey), true);
						$state='1';
						$text='注册成功!接入成功!';
					}else{
						message($request_arr['text']);
					}
				}else if($_GPC['type']==='mapconf'){
					//查询bdkey
					$bdkey=$this->getBdkey();
					//生成要发送的数据
					$data=array('type'=>'addmapkey','manage'=>1,'bdkey'=>$bdkey);
					if(!empty($_GPC['amap-server'])){
						$data['amap_server']=$_GPC['amap-server'];
					}
					if(!empty($_GPC['amap-web'])){
						$data['amap_web']=$_GPC['amap-web'];
					}
					if(!empty($_GPC['baidu-server'])){
						$data['baidu_server']=$_GPC['baidu-server'];
					}
					$request=$this->postRequest($data);
					$request_arr=json_decode($request,true);
					if($request_arr['state']==='ok'){
						pdo_update('bus_info', array('map_amap_server_key'=>$_GPC['amap-server'] , 'map_amap_web_key'=>$_GPC['amap-web'] , 'map_baidu_server_key'=>$_GPC['baidu-server']) , array('weid'=>$_W['weid']));
						$state='1';
						$text='设置成功!';
					}else{
						message($request_arr['text']);
					}
				}else{
					message("请求类型错误!");
				}
			}
		}else{
			//查询bdkey
			$bdkey=$this->getBdkey(1);
			//判断bdkey是否为空
			if(empty($bdkey)){
				$text='使用系统前必须先完成接入操作!';
			}else{
				$map_amap_server_key=$bdkey['map_amap_server_key'];
				$map_amap_web_key=$bdkey['map_amap_web_key'];
				$map_baidu_server_key=$bdkey['map_baidu_server_key'];
				if(empty($map_amap_server_key) or empty($map_amap_web_key) or empty($map_baidu_server_key)){
					$state='2';
					$text='请设置地图信息!';
				}else{
					$state='1';
					$text='系统已接入!';
				}
			}
		}
		include $this->template('access-manager');
	}
	//企业信息管理页
	public function doWebCompanyManager(){
		global $_GPC,$_W;
		//查询bdkey
		$bdkey=$this->getBdkey();
		if(!empty($_GPC['type'])){
			if($_GPC['type']=='addcompany'){
				//生成要发送的数据
				$data=array('type'=>'addcompany','manage'=>1,'bdkey'=>$bdkey);
				if(empty($_GPC['name'])){
					message('企业/分组名称不可为空');
				}
				//保存名称
				$data['company_name']=$_GPC['name'];
				if(!isset($_GPC['state'])){
					message('企业/分组状态不可为空');
				}else{
					if($_GPC['state']!=='0' and $_GPC['state']!=='1'){
						message('企业/分组状态错误');
					}
				}
				//保存企业/分组状态
				$data['state']=$_GPC['state'];
				if(!isset($_GPC['is-check'])){
					message('打卡状态不可为空');
				}else{
					if($_GPC['is-check']!=='0' and $_GPC['is-check']!=='1'){
						message('打卡状态错误');
					}
					//保存打卡状态
					$data['is_check']=$_GPC['is-check'];
					//判断是否需要打卡
					if($_GPC['is-check']==='1'){
						if(empty($_GPC['check-longitude'])){
							message('打卡经度错误');
						}
						if(empty($_GPC['check-latitude'])){
							message('打卡纬度错误');
						}
						if(empty($_GPC['check-longitude-error'])){
							message('打卡经度误差错误');
						}
						if(empty($_GPC['check-latitude-error'])){
							message('打卡纬度误差错误');
						}
						if(empty($_GPC['check-start-time'])){
							message('打卡开始时间错误');
						}
						if(empty($_GPC['check-stop-time'])){
							message('打卡结束时间错误');
						}
						//保存打卡详细信息
						$data['check_longitude']=$_GPC['check-longitude'];
						$data['check_latitude']=$_GPC['check-latitude'];
						$data['check_longitude_error']=$_GPC['check-longitude-error'];
						$data['check_latitude_error']=$_GPC['check-latitude-error'];
						$data['check_start_time']=$_GPC['check-start-time'];
						$data['check_stop_time']=$_GPC['check-stop-time'];
					}
				}
			}else if($_GPC['type']=='changecompany'){
				if(!empty($_GPC['company_id'])){
					//生成要发送的数据
					$data=array('type'=>'changecompany','manage'=>1,'bdkey'=>$bdkey,'company_id'=>$_GPC['company_id']);
					if(empty($_GPC['name'])){
						message('企业/分组名称不可为空');
					}
					//保存名称
					$data['company_name']=$_GPC['name'];
					if(!isset($_GPC['state'])){
						message('企业/分组状态不可为空');
					}else{
						if($_GPC['state']!=='0' and $_GPC['state']!=='1'){
							message('企业/分组状态错误');
						}
					}
					//保存企业/分组状态
					$data['state']=$_GPC['state'];
					if(!isset($_GPC['is-check'])){
						message('打卡状态不可为空');
					}else{
						if($_GPC['is-check']!=='0' and $_GPC['is-check']!=='1'){
							message('打卡状态错误');
						}
						//保存打卡状态
						$data['is_check']=$_GPC['is-check'];
						//判断是否需要打卡
						if($_GPC['is-check']==='1'){
							if(empty($_GPC['check-longitude'])){
								message('打卡经度错误');
							}
							if(empty($_GPC['check-latitude'])){
								message('打卡纬度错误');
							}
							if(empty($_GPC['check-longitude-error'])){
								message('打卡经度误差错误');
							}
							if(empty($_GPC['check-latitude-error'])){
								message('打卡纬度误差错误');
							}
							if(empty($_GPC['check-start-time'])){
								message('打卡开始时间错误');
							}
							if(empty($_GPC['check-stop-time'])){
								message('打卡结束时间错误');
							}
							//保存打卡详细信息
							$data['check_longitude']=$_GPC['check-longitude'];
							$data['check_latitude']=$_GPC['check-latitude'];
							$data['check_longitude_error']=$_GPC['check-longitude-error'];
							$data['check_latitude_error']=$_GPC['check-latitude-error'];
							$data['check_start_time']=$_GPC['check-start-time'];
							$data['check_stop_time']=$_GPC['check-stop-time'];
						}
					}
				}else{
					message('参数错误！');
				}
			}else{
				message('请求错误');
			}
			//发送查询，获取请求结果
			$add_company_request=$this->postRequest($data);
			//解析结果
			$add_company_request_arr=json_decode($add_company_request,true);
			//判断请求是否成功
			if($add_company_request_arr['state']!=='ok'){
				message($add_company_request_arr['text']);
			}else{
				$state='1';
				$text='操作成功！';
			}
		}
		//查询企业/分组信息
		$company_info_request=$this->postRequest(array('type'=>'querycompany' , 'manage'=>'1' , 'bdkey'=>$bdkey));
		//解析结果
		$company_info_request_arr=json_decode($company_info_request,true);
		//判断请求是否成功
		if($company_info_request_arr['state']!=='ok'){
			$text=$company_info_request_arr['text'];
		}else{
			if(!isset($state)){
				$state='1';
				$text='查询成功！';
			}
		}
		include $this->template('company-manager');
	}
	//班车打卡信息导出
	public function doWebInformation(){
		global $_GPC,$_W;
		//查询bdkey
		$bdkey=$this->getBdkey();
		//如果为空
		if(empty($bdkey)){
			message('服务还未完成接入!','', 'error');
		}
		//发送查询请求
		$html=$this->postRequest(array(
									'type'=>'userquerybus',
									'manage'=>1,
									'bdkey'=>$bdkey,
									'type'=>'buscheckinfo',
								));
		$demodulation=json_decode($html,true);
		if($demodulation['state']!=='ok'){
			message($demodulation['text'],'', 'error');
		}
		/* 输入到CSV文件 */
		$html = "\xEF\xBB\xBF";
		/* 输出表头 */
		$filter = array(
			'id' => '编号',
			'company' => '单位名称',
			'route_num' => '班车线路',
			'plate_num' => '车牌号',
			'statustime' => '到达时间',
		);
		//赋值
		foreach($filter as $key  => $title){
			$html .= $title . "\t,";
		}
		//循环企业
		for($a=0;$a<count($demodulation['check_info']);$a++){
			//循环企业车辆
			foreach($demodulation['check_info'][$a]['detail'] as $k => $v){
					$html .= "\n";
					$html .= $v['id'] . "\t, ";
					$html .= $demodulation['check_info'][$a]['company_name'] . "\t, ";
					$html .= $v['route_num']. "\t, ";
					$html .= $v['plate_num']. "\t, ";
					$html .= date('Y-m-d H:i:s', $v['statustime']) . "\t, ";
			}
		}
		/* 输出CSV文件 */
		header("Content-type:text/csv");
		header("Content-Disposition:attachment; filename=班车数据.csv");
		return $html;
	}
	/*
	获取地图信息
	@string state=1时只返回GPS等信息,不返回调用地图
	*/
	public function doMobileGetMap(){
		global $_GPC,$_W;
		if(empty($_GPC['login_openid'])){
			$openid=$_W['openid'];
		}else{
			$openid=$_GPC['login_openid'];
		}
		//查询bdkey,地图密钥等信息
		$info=$this->getBdkey(1);
		//发送查询请求
		$html=$this->postRequest(array(
									'type'=>'userquerybus',
									'bdkey'=>$info['bdkey'],
									'gzh_openid'=>$openid,
								));
		$demodulation=json_decode($html,true);
		//获取成功
		if($demodulation['state']=='ok'){
			$convert_url = "http://restapi.amap.com/v3/assistant/coordinate/convert?coordsys=gps&key=".$info['map_amap_server_key']."&locations=".$demodulation['bus_info']['longitude'].",".$demodulation['bus_info']['latitude'];
			$result = file_get_contents($convert_url);
			$gps_info = json_decode($result, true);
			$gps = explode(",", $gps_info['locations']);
			//要返回的信息
			$return_info=array('state'=>'ok','longitude'=>$gps[0],'latitude'=>$gps[1],'plate_num'=>$demodulation['bus_info']['plate_num'],'speed'=>$demodulation['bus_info']['speed'],'location_time'=>$demodulation['bus_info']['location_time']);
			if(empty($_GPC['state'])){
				$map_amap_web_key=$info['map_amap_web_key'];
				include $this->template('map-static');
			}else{
				return $this->encode($return_info);
			}
		//未绑定
		}else if($demodulation['state']=='no_binding'){
			$state='您还未绑定班车!';
		//其他状态
		}else{
			$state=$demodulation['text'];
		}
	}
	/*
	进入历史记录查询界面
	*/
	public function doMobileGetLocationsLog(){
		global $_GPC,$_W;
		$info=$this->getBdkey(1);
		$bdkey=$info['bdkey'];
		//查询车辆最后位置
		$last_locations_url = SERVERURL."?type=admingetdevicelocations&bdkey=".$bdkey."&gzh_openid=".$_W['openid']."&map_type=amap&bus_id=1&query_type=0";
		$last_locations_result = file_get_contents($last_locations_url);
		$last_locations = json_decode($last_locations_result, true);
		//查询车辆列表
		$convert_url = SERVERURL."?type=admintodevice&bdkey=".$bdkey."&gzh_openid=".$_W['openid'];
		$result = file_get_contents($convert_url);
		$temp = json_decode($result, true);
		$dev_list=$temp['dev_list'];
		if($temp['state']=='ok'){
			$map_amap_web_key=$info['map_amap_web_key'];
			include $this->template('locations-log');
		}else{
			message($temp['text'],'','error');
		}
	}
	/*
	进入历史记录查询界面
	@text $_GPC['bus_id'] bus_list中的ID
	@text $_GPC['start_time'] 查询时间
	*/
	public function doMobileGetDeviceLocations(){
		global $_GPC,$_W;
		$bus_id=$_GPC['bus_id'];
		$start_time=$_GPC['start_time'];
		$convert_url = SERVERURL."?type=admingetdevicelocations&map_type=amap&bdkey=".$this->getBdkey()."&gzh_openid=".$_W['openid']."&bus_id=".$bus_id."&start_time=".$start_time."&query_type=1";
		$result = file_get_contents($convert_url);
		return $result;
	}

	//获取设备连接信息
	public function doMobileGetConnectInfo(){
		global $_GPC,$_W;
		//查询bdkey
		$bdkey=$this->getBdkey();
		//如果为空
		if(empty($bdkey)){
			message('服务还未完成接入!','', 'error');
		}
		//发送查询请求
		$html=$this->postRequest(array(
									'type'=>'querybusall',
									'manage'=>1,
									'bdkey'=>$bdkey,
								));
		$demodulation=json_decode($html,true);
		$r['columns']=array(
							array(
									"name"=>"company_name",
									"type"=>"string",
									"friendly_name"=>"company",
									),
							array(
									"name"=>"route",
									"type"=>"string",
									"friendly_name"=>"route",
									),
							array(
									"name"=>"route_num",
									"type"=>"string",
									"friendly_name"=>"route_num",
									),
							array(
									"name"=>"plate_num",
									"type"=>"string",
									"friendly_name"=>"plate_num",
									),
							array(
									"name"=>"imei",
									"type"=>"string",
									"friendly_name"=>"imei",
									),
							array(
									"name"=>"is_online",
									"type"=>"string",
									"friendly_name"=>"is_online",
									),
							array(
									"name"=>"login_time",
									"type"=>"string",
									"friendly_name"=>"login_time",
									),
							array(
									"name"=>"location_time",
									"type"=>"string",
									"friendly_name"=>"location_time",
									),
							);
		$r['rows']=$demodulation['bus_info'];
		return($this->encode($r));
	}
}
