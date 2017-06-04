<?php
/**
 * 
 */
defined('IN_IA') or exit('Access Denied');

class Vittor_busModule extends WeModule {


/* 	public $name = 'Bus';
	public $title = '班车查询';
	public $ability = '';
	public $table_reply  = 'bus_reply';
	public $table_list  = 'bus_list'; */


	public function fieldsFormDisplay($rid = 0) {
		/* global $_W , $_GPC;
		if($rid) {
			$reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid", array(':rid' => $rid));
		}
		include $this->template('rule'); */
	}

	public function fieldsFormValidate($rid = 0) {

	}

	
	public function fieldsFormSubmit($rid) {
		/* global $_GPC;
		global $_W;
		$type = intval($_GPC['type']);
		$record = array();
		$record['type'] = $type;
		$record['rid'] = $rid;
		$record['weid'] = $_W['weid'];
		$reply = pdo_fetch("SELECT * FROM " . tablename($this->table_reply) . " WHERE rid = :rid", array(':rid' => $rid));
		if($reply) {
			pdo_update($this->table_reply, $record, array('id' => $reply['id']));
		} else {
			pdo_insert($this->table_reply, $record);
		} */
	}

	public function ruleDeleted($rid) {
		/* pdo_delete($this->table_reply, array('rid' => $rid)); */
	}


}
