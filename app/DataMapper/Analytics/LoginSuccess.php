<?php

namespace App\DataMapper\Analytics;

class LoginSuccess
{

	/**
	 * The type of Sample
	 *
	 * Monotonically incrementing counter
	 * 
	 * 	- counter
	 * 	
	 * @var string
	 */
	public $type = 'counter';

	/**
	 * The name of the counter
	 * @var string
	 */
	public $name = 'login.success';

	/**
	 * The datetime of the counter measurement
	 *
	 * date("Y-m-d H:i:s")
	 * 
	 * @var DateTime 
	 */
	public $datetime;

	/**
	 * The increment amount... should always be 
	 * set to 0
	 * 
	 * @var integer
	 */
	public $metric = 0;

}