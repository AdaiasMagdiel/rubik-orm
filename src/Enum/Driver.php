<?php

namespace AdaiasMagdiel\Rubik\Enum;

/**
 * Enumerates the supported database drivers for the Rubik ORM.
 *
 * This enum is used to specify which database engine the Rubik class should
 * connect to and configure (e.g., during Rubik::connect()).
 *
 * @package AdaiasMagdiel\Rubik\Enum
 */
enum Driver: string
{
	/**
	 * SQLite database driver.
	 */
	case SQLITE = 'SQLITE';

	/**
	 * MySQL or MariaDB database driver.
	 */
	case MYSQL = 'MYSQL';
}
