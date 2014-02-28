<?php

namespace dinhtrung\setting;

/**
 * Settings Model
 *
 *
 * DATABASE STRUCTURE:
 *
 * CREATE TABLE IF NOT EXISTS `setting` (
 * [[category]] varchar(64) NOT NULL default 'system',
 * [[key]] varchar(255) NOT NULL,
 * [[value]] text NOT NULL,
 * PRIMARY KEY (`id`),
 * KEY `category_key` ([[category]],[[key]])
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
 */
use Yii;
use yii\db\Schema;
use yii\base\Application;

class Setting extends \yii\base\Component {
	public $setting_table = 'setting';
	protected $_toBeSave = array ();
	protected $_toBeDelete = array ();
	protected $_deleteCategoriesFromDatabase = array ();
	protected $_cacheFlush = array ();
	protected $_items = array ();

	/**
	 * Setting::init()
	 *
	 * @return
	 *
	 */
	public function init() {
		if (YII_DEBUG && ! Yii::$app->getDb ()->getTableSchema ( "{{%" . $this->setting_table . "}}" )) {
			Yii::$app->db->createCommand (
				\Yii::$app->getDb()->getQueryBuilder()->createTable ( "{{%" . $this->setting_table . "}}", [
					'category' => Schema::TYPE_STRING,
					'key' => Schema::TYPE_STRING,
					'value' => Schema::TYPE_TEXT,
				]
			)
			 )->execute ();
			Yii::$app->db->createCommand (
				Yii::$app->getDb ()->getQueryBuilder()->addPrimaryKey( 'category_key', "{{%" . $this->setting_table . "}}", 'category,key' )
			)->execute ();
		}
		$this->on (Application::EVENT_AFTER_REQUEST, [$this, 'commit']);
	}

	/**
	 * Setting::set()
	 *
	 * @param string $category
	 * @param mixed $key
	 * @param string $value
	 * @param bool $toDatabase
	 * @return
	 *
	 */
	public function set($category = 'system', $key, $value = '', $toDatabase = true) {
		if (is_array ( $key )) {
			foreach ( $key as $k => $v )
				$this->set ( $category, $k, $v, $toDatabase );
		} else {
			if ($toDatabase)
				$this->_toBeSave [$category] [$key] = $value;
			$this->_items [$category] [$key] = $value;
		}
	}

	/**
	 * Setting::get()
	 *
	 * @param string $category
	 * @param string $key
	 * @param string $default
	 * @return
	 *
	 */
	public function get($category = 'system', $key = '', $default = '') {
		if (! isset ( $this->_items [$category] ))
			$this->load ( $category );

		if (empty ( $key ) && empty ( $default ) && ! empty ( $category ))
			return isset ( $this->_items [$category] ) ? $this->_items [$category] : null;

		if (isset ( $this->_items [$category] [$key] ))
			return $this->_items [$category] [$key];
		return ! empty ( $default ) ? $default : null;
	}

	/**
	 * Setting::delete()
	 *
	 * @param string $category
	 * @param string $key
	 * @return
	 *
	 */
	public function delete($category = 'system', $key = '') {
		if (! empty ( $category ) && empty ( $key )) {
			$this->_deleteCategoriesFromDatabase [] = $category;
			return;
		}
		if (is_array ( $key )) {
			foreach ( $key as $k )
				$this->delete ( $category, $k );
		} else {
			if (isset ( $this->_items [$category] [$key] )) {
				unset ( $this->_items [$category] [$key] );
				$this->_toBeDelete [$category] [] = $key;
			}
		}
	}

	/**
	 * Setting::load()
	 *
	 * @param mixed $category
	 * @return
	 *
	 */
	public function load($category) {
		$items = Yii::$app->cache->get ( $category . '_setting' );
		if (! $items) {
			$result = Yii::$app->getDb()->createCommand (
					"SELECT * FROM {{%" . $this->setting_table . "}} WHERE [[category]]=:cat", [
						':cat' => $category,
				])->queryAll();
			if (empty ( $result )) {
				$this->set ( $category, '{empty}', '{empty}', false );
				return;
			}

			$items = array ();
			foreach ( $result as $row )
				$items [$row ['key']] = @unserialize ( $row ['value'] );

			Yii::$app->cache->add ( $category . '_setting', $items );
		}
		$this->set ( $category, $items, '', false );
		return $items;
	}

	/**
	 * Setting::toArray()
	 *
	 * @return
	 *
	 */
	public function toArray() {
		return $this->_items;
	}

	/**
	 * Setting::addDbItem()
	 *
	 * @param string $category
	 * @param mixed $key
	 * @param mixed $value
	 * @return
	 *
	 */
	private function addDbItem($category = 'system', $key, $value) {
		$result = Yii::$app->getDb()->createCommand(
				"SELECT * FROM {{%" . $this->setting_table . "}} WHERE [[category]]=:cat AND [[key]]=:key LIMIT 1", [
					':cat' => $category,
					':key' => $key
			] )->queryOne();
		$_value = @serialize ( $value );
		\Yii::info(\yii\helpers\VarDumper::dumpAsString($result));

		if (! $result ) {
			$command = Yii::$app->getDb()->createCommand (
					"INSERT INTO {{%" . $this->setting_table . "}} ([[category]], [[key]], [[value]]) VALUES(:cat,:key,:value)",
					[
						':cat' => $category,
						':key' => $key,
						':value' => $_value
					] )->execute();
		} else {
			$command = Yii::$app->getDb()->createCommand (
					"UPDATE {{%" . $this->setting_table . "}} SET [[value]]=:value WHERE [[category]]=:cat AND [[key]]=:key",
					[
						':cat' => $category,
						':key' => $key,
						':value' => $_value
					] )->execute();
		}
	}

	/**
	 * @return
	 *
	 */
	public function commit() {
		$this->_cacheFlush = array ();

		if (count ( $this->_deleteCategoriesFromDatabase ) > 0) {
			foreach ( $this->_deleteCategoriesFromDatabase as $catName ) {
				$command = Yii::$app->getDb()->createCommand (
					"DELETE FROM {{%" . $this->setting_table . "}} WHERE [[category]]=:cat", [
						':cat' => $catName
					] )->execute();
				$this->_cacheFlush [] = $catName;

				if (isset ( $this->_toBeDelete [$catName] ))
					unset ( $this->_toBeDelete [$catName] );
				if (isset ( $this->_toBeSave [$catName] ))
					unset ( $this->_toBeSave [$catName] );
			}
		}

		if (count ( $this->_toBeDelete ) > 0) {
			foreach ( $this->_toBeDelete as $catName => $keys ) {
				$params = array ();
				$i = 0;
				foreach ( $keys as $v ) {
					if (isset ( $this->_toBeSave [$catName] [$v] ))
						unset ( $this->_toBeSave [$catName] [$v] );
					$params [':p' . $i] = $v;
					++ $i;
				}
				$names = implode ( ',', array_keys ( $params ) );

				$command = Yii::$app->getDb()->createCommand(
						"DELETE FROM {{%" . $this->setting_table . "}} WHERE [[category]]=:cat AND [[key]] IN ($names)", [
						':cat' => $catName
				]);
				foreach ( $params as $key => $value )
					$command->bindParam ( $key, $value );

				$command->execute ();
				$this->_cacheFlush [] = $catName;
			}
		}

		if (count ( $this->_toBeSave ) > 0) {
			foreach ( $this->_toBeSave as $catName => $keyValues ) {
				foreach ( $keyValues as $k => $v )
					$this->addDbItem ( $catName, $k, $v );
				$this->_cacheFlush [] = $catName;
			}
		}

		if (count ( $this->_cacheFlush ) > 0) {
			foreach ( $this->_cacheFlush as $catName )
				Yii::$app->cache->delete ( $catName . '_setting' );
		}
	}
}
