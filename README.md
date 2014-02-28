Yii2 Setting Component
======================
Store settings in the database

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist dinhtrung/yii2-setting "*"
```

or add

```
"dinhtrung/yii2-setting": "*"
```

to the require section of your `composer.json` file.


Usage
-----

In your configuration file, add the setting component.

```php
'components' => [
	...
	'setting' => 'dinhtrung\setting\Setting',
	...
]
```

You can choose which table to store the setting item, which will be auto-generate on demand.

```php
'components' => [
	...
	'setting' => [
		'class' => 'dinhtrung\setting\Setting',
		'setting_table' => 'website_setting',
	]
	...
]
```

In anywhere from your code, you can use those features:

```php
$setting = Yii::$app->setting->get('category', 'key', 'default value');
$setting = Yii::$app->setting->set('category', 'key', 'new value');
```

Or you can query all setting as one

```php
$settingArray = Yii::$app->setting->get('category', 'key');
$settingArray = Yii::$app->setting->get('category', 'key', 'default value');
$settingArray = Yii::$app->setting->set('category', [
	'key1' => 'value1',
	'key2' => 'value2',
	'key3' => 'value3',
]);
```