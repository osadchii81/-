<?php

include_once 'common.php';

// Получаем идентификатор категории и номер страницы
$id = isset($_GET['id']) ? $_GET['id'] : 0;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
if ($page < 1) $page = 1;
// Разбираемся с сортировкой
$order_line = isset($_GET['order']) ? $_GET['order'] : '';
$orderby = 'added_at';
$order_direction = 'DESC';
if ($order_line == 'price-desc') {
	$orderby = 'price';
	$order_direction = 'DESC';
}
elseif ($order_line == 'price-asc') {
	$orderby = 'price';
	$order_direction = 'ASC';
}
elseif ($order_line == 'orders') {
	$orderby = 'orders_count';
	$order_direction = 'DESC';
}
else {
	// На всякий случай. Чтобы не плодить урлы с фигнёй
	$order_line = '';
}

$categories_list = array();
$offers = array();
$total_offers = 0;


// Готовим обращение к API
//------------------------------------------------------------------------------
$cache_key_categories = $Cache->PrepareCacheKey(array(
	'for' => 'categories',
	'lang' => $settings['lang'],
));
if (!$categories_list = $Cache->Get($cache_key_categories)) {
	$APIAccess->AddRequestCategoriesList('categories', $settings['lang']);
}
//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
$search_params = array(
		'query' => '',
		'offset' => 0,
		'limit' => $settings['items_per_page'],
		'offset' => ($page-1)*$settings['items_per_page'],
		'category' => $id,
		'price_min' => $settings['price_min'],
		'price_max' => $settings['price_max'],
		'orderby' => $orderby,
		'order_direction' => $order_direction,
		'lang' => $settings['lang'],
		'currency' => $settings['currency'],
	);
$cache_key_search = $Cache->PrepareCacheKey($search_params);

$offers_tmp = FALSE;
if (!$offers_tmp = $Cache->Get($cache_key_search)) {
	$APIAccess->AddRequestSearch('search', $search_params);
}
else {
	$offers = $offers_tmp['offers'];
	$total_offers = $offers_tmp['total_found'];
}
//------------------------------------------------------------------------------


// Выполняем запрос к API
if ($APIAccess->RunRequests()) {
	// Достаём данные
	if (($categories_list_tmp = $APIAccess->GetRequestResult('categories')) && isset($categories_list_tmp['categories'])) {
		$categories_list = $categories_list_tmp['categories'];
		$Cache->Set($cache_key_categories, $categories_list, 86400);
	}
	// Достаём данные
	if (($offers_tmp = $APIAccess->GetRequestResult('search')) && isset($offers_tmp['offers'])) {
		$offers = $offers_tmp['offers'];
		$total_offers = $offers_tmp['total_found'];
		$Cache->Set($cache_key_search, $offers_tmp, 14400);
	}
}
// Если что-то пошло не так и ошибка в данных, а не сетевая
elseif ($APIAccess->LastErrorType() == 'data') {
	print "Проблема при обращении к API ePN: <br />\n";
	print $APIAccess->LastError() . "\n";
	exit();
}
// Если что-то пошло не так и ошибка сетевая
elseif ($APIAccess->LastErrorType() == 'network') {
	print "Сетевая проблема при обращении к API ePN: <br />\n";
	print $APIAccess->LastError() . "\n";
	exit();
}



// Здесь будет хэш id => info
$categories_hash = array();
// Здесь будет информация о текущей категории
$current_category = FALSE;
// Дополняем данные
foreach ($categories_list as $key => $value) {
	$categories_list[$key]['link'] = $Path->Category($value['id'], $value['title']);
	$categories_list[$key]['current'] = ($value['id'] == $id);
	$categories_hash[$value['id']] = $categories_list[$key];
	if ($value['id'] == $id) {
		$current_category = $categories_list[$key];
		$current_category['count'] = $total_offers;
	}
}

// Дополняем информацию о товарах
foreach ($offers as $key => $value) {
	// Информация о категории
	$offers[$key]['category'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['title'] : '';
	$offers[$key]['category_link'] = isset($categories_hash[$value['id_category']]) ? $categories_hash[$value['id_category']]['link'] : '';
	// "Прямая" ссылка
	$offers[$key]['url'] = $Path->Go($value['id']);
	// Ссылка на более подробную информацию
	$offers[$key]['link'] = $Path->Offer($value['id'], $value['name']);
}

// Строим пейджер
$pages = array();
$orders = array();
// Общее число страниц
$page_count = ceil($total_offers / $settings['items_per_page']);
// А нужен ли вообще пейджер и сортировоки?
if ($current_category && $page_count > 1) {
	$page_min = $page - 4;
	if ($page_min < 1) $page_min = 1;
	
	$page_max = $page + 4;
	if ($page_max > $page_count) $page_max = $page_count;
	
	if ($page_min > 1) {
		$pages[] = array(
				'page' => '<<',
				'link' => $Path->Category($id, $current_category['title'], 1, $order_line),
			);
	}
	
	if ($page > 1) {
		$pages[] = array(
				'page' => '<',
				'link' => $Path->Category($id, $current_category['title'], $page - 1, $order_line),
			);
	}
	
	for ($i = $page_min; $i <= $page_max; $i++) {
		$pages[] = array(
				'page' => $i,
				'link' => $i == $page ? '' : $Path->Category($id, $current_category['title'], $i, $order_line),
			);
	}

	if ($page < $page_count) {
		$pages[] = array(
				'page' => '>',
				'link' => $Path->Category($id, $current_category['title'], $page + 1, $order_line),
			);
	}
	
	if ($page_max < $page_count) {
		$pages[] = array(
				'page' => '>>',
				'link' => $Path->Category($id, $current_category['title'], $page_count, $order_line),
			);
	}
	
	// Сортировки
	$orders = array(
		array(
			'title' => $Lang->GetString('default'),
			'order' => '',
			'nofollow' => FALSE,
		),
		array(
			'title' => $Lang->GetString('price lower'),
			'order' => 'price-asc',
			'nofollow' => TRUE,
		),
		array(
			'title' => $Lang->GetString('price higher'),
			'order' => 'price-desc',
			'nofollow' => TRUE,
		),
		array(
			'title' => $Lang->GetString('popular'),
			'order' => 'orders',
			'nofollow' => TRUE,
		),
	);
	foreach ($orders as $key => $value) {
		$orders[$key]['url'] = $Path->Category($id, $current_category['title'], 1, $value['order']);
		$orders[$key]['current'] = ($value['order'] == $order_line);
	}
}


// Цепляем шаблон
include_once $Common->GetTemplatePath('category');

