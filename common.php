<?php
// Настройки по умолчанию
$default_settings = array(
	'user_api_key' => '',
	'user_deep_link_hash' => '',
	'items_per_page' => 15,
	'rss_items_count' => 15,
	'price_min' => 0.0,
	'price_max' => 1000000.0,
	'site_name' => 'Universe of goods',
	'lang' => 'en',
	'currency' => 'USD',
	'cache_library' => 'none',
	'memcached_host' => '127.0.0.1',
	'memcached_port' => 11211,
	'memcached_pconnect' => TRUE,
	'mysql_host' => '127.0.0.1',
	'mysql_user' => 'root',
	'mysql_pass' => '',
	'mysql_base' => 'test',
	'mysql_persist' => FALSE,
);



if (!file_exists(dirname(__FILE__) . '/config.php')) {
	print "Пожалуйста создайте файл \"config.php\". Если вы не знаете как это сделать - обратитесь к документации ePN CMS.";
	exit();
}

// Настройки
include_once dirname(__FILE__) . '/config.php';

// Объединяем считанные настройки со значениями по умолчанию
foreach ($default_settings as $key => $value) {
	if (!isset($settings[$key])) $settings[$key] = $value;
}

// Базовые функции
include_once dirname(__FILE__) . '/libs/clCommon.php';

$Common = new clCommon();

// Проверяем наличие всего необходимого
$Common->CheckCMSRequirements();

//------------------------------------------------------------------------------
// Цепляем библиотеку для работы с API
include_once dirname(__FILE__) . '/libs/clEPNAPIAccess.php';

// Класс для работы с API
$APIAccess = new clEPNAPIAccess(
                trim($settings['user_api_key']),
                trim($settings['user_deep_link_hash'])
        );
//------------------------------------------------------------------------------


//------------------------------------------------------------------------------
// Цепляем библиотеку для работы с путями
include_once dirname(__FILE__) . '/libs/clPath.php';

// Создаём объект для работы с путями
$Path = new clPath(
		$settings['user_deep_link_hash']
	);
//------------------------------------------------------------------------------


//------------------------------------------------------------------------------
// Библиотека для работы с языками
include_once dirname(__FILE__) . '/libs/clLang.php';

// Создаём объект для работы с языками
$Lang = new clLang(
		$settings['lang']
	);
//------------------------------------------------------------------------------


//------------------------------------------------------------------------------
// Разбор случаев для кэша
$Cache = FALSE;
switch ($settings['cache_library']) {
	// Будем кэшировать в XCache
	case 'xcache';
		include_once dirname(__FILE__) . '/libs/clCacheXCache.php';
		$Cache = new clCacheXCache();
	break;
	
	// Будем кэшировать в APC user cache
	case 'apc';
		include_once dirname(__FILE__) . '/libs/clCacheAPC.php';
		$Cache = new clCacheAPC();
	break;
	
	// Будем кэшировать в WinCache. Важно! Экспериментальная поддержка! Требуется тестирование!
	case 'wincache';
		include_once dirname(__FILE__) . '/libs/clCacheWinCache.php';
		$Cache = new clCacheWinCache();
	break;
	// Будем кэшировать в memcached, используя библиотеку Memcache
	case 'memcache';
		include_once dirname(__FILE__) . '/libs/clCacheMemcache.php';
		$Cache = new clCacheMemcache(
				$settings['memcached_host'],
				$settings['memcached_port'],
				$settings['memcached_pconnect']
			);
	break;
	
	// Будем кэшировать в memcached, используя библиотеку Memcached
	case 'memcached';
		include_once dirname(__FILE__) . '/libs/clCacheMemcached.php';
		$Cache = new clCacheMemcached(
				$settings['memcached_host'],
				$settings['memcached_port'],
				$settings['memcached_pconnect']
			);
	break;
	
	// Будем кэшировать в MySQL
	case 'mysql';
		include_once dirname(__FILE__) . '/libs/clCacheMySQL.php';
		$Cache = new clCacheMySQL(
				$settings['mysql_host'],
				$settings['mysql_user'],
				$settings['mysql_pass'],
				$settings['mysql_base'],
				$settings['mysql_persist']
			);
	break;
	
	// Вариант по умолчанию. Фейковый кэш (отсутствие кэширования)
	default;
		include_once dirname(__FILE__) . '/libs/clCacheFake.php';
		$Cache = new clCacheFake();
	break;
}
//------------------------------------------------------------------------------