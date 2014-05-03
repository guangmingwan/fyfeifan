<?php

/**
 * ECSHOP 会员中心
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: user.php 17217 2011-01-19 06:29:08Z liubo $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/../includes/init.php');
require_once(ROOT_PATH . 'includes/lib_order.php');

/* 载入语言文件 */
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/user.php');

$user_id = $_SESSION['user_id'];

header('Access-Control-Allow-Origin: *');
$surplus_amount = get_user_surplus($user_id);
$order_sn = $_GET["oid"];
$order = order_info(0,$order_sn);
$amount = $order['order_amount'];
if (isset($_POST)) {
	/* 检查是否登录 */
	$error = 0;
	$canbuy = 1;
	if ($_SESSION['user_id'] <= 0)
	{
		$error = 100;
		$canbuy = 0;
	}
	
	/* 检查订单号 */
	$order_id = intval($_POST['order_id']);
	if ($order_id <= 0)
	{
		$error = 101;
		$canbuy = 0;
	}
	

	/* 取得订单 */
	 
	if (empty($order))
	{
		$error = 102;
		$canbuy = 0;
	}
	
	/* 检查订单用户跟当前用户是否一致 */
	else if ($_SESSION['user_id'] != $order['user_id'])
	{
		$error = 103;
		$canbuy = 0;
	}
	
	/* 检查订单是否未付款，检查应付款金额是否大于0 */
	else if ($order['pay_status'] != PS_UNPAYED || $order['order_amount'] <= 0)
	{
		$err->add($_LANG['error_order_is_paid']);
		$error = 104;
		$canbuy = 0;
		
	}
	else {
		/* 计算应付款金额（减去支付费用） */
		$order['order_amount'] -= $order['pay_fee'];
		
		/* 余额是否超过了应付款金额，改为应付款金额 */
		//if ($surplus > $order['order_amount'])
		//{
		$surplus = $order['order_amount'];
		//}
		
		/* 取得用户信息 */
		$user = user_info($_SESSION['user_id']);
		
		/* 用户帐户余额是否足够 */
		if ($surplus > $user['user_money'] + $user['credit_line'])
		{
			$err->add($_LANG['error_surplus_not_enough']);
			$error = 105;
			$canbuy = 0;
		}
	}
	if($error == 0) {
		/* 修改订单，重新计算支付费用 */
		$order['surplus'] += $surplus;
		$order['order_amount'] -= $surplus;
		if ($order['order_amount'] > 0)
		{
			$cod_fee = 0;
			if ($order['shipping_id'] > 0)
			{
				$regions  = array($order['country'], $order['province'], $order['city'], $order['district']);
				$shipping = shipping_area_info($order['shipping_id'], $regions);
				if ($shipping['support_cod'] == '1')
				{
					$cod_fee = $shipping['pay_fee'];
				}
			}
		
			$pay_fee = 0;
			if ($order['pay_id'] > 0)
			{
				$pay_fee = pay_fee($order['pay_id'], $order['order_amount'], $cod_fee);
			}
		
			$order['pay_fee'] = $pay_fee;
			$order['order_amount'] += $pay_fee;
		}
		
		/* 如果全部支付，设为已确认、已付款 */
		if ($order['order_amount'] == 0)
		{
			if ($order['order_status'] == OS_UNCONFIRMED)
			{
				$order['order_status'] = OS_CONFIRMED;
				$order['confirm_time'] = gmtime();
			}
			$order['pay_status'] = PS_PAYED;
			$order['pay_time'] = gmtime();
		}
		$order = addslashes_deep($order);
		update_order($order_id, $order);
		
		/* 更新用户余额 */
		$change_desc = sprintf($_LANG['pay_order_by_surplus'], $order['order_sn']);
		log_account_change($user['user_id'], (-1) * $surplus, 0, 0, 0, $change_desc);
		
		 
	 
	} 
	$data = array("canbuy"=>$canbuy,"error"=>$error);
	echo json_encode($data);
}
 
