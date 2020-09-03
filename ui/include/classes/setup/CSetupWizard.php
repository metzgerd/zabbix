<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CSetupWizard extends CForm {

	const VAULT_HOST_DEFAULT = 'https://localhost:8200';

	function __construct() {
		$this->DISABLE_CANCEL_BUTTON = false;
		$this->DISABLE_BACK_BUTTON = false;
		$this->SHOW_RETRY_BUTTON = false;
		$this->STEP_FAILED = false;
		$this->frontendSetup = new CFrontendSetup();

		$this->stage = [
			0 => [
				'title' => _('Welcome'),
				'fnc' => 'stage0'
			],
			1 => [
				'title' => _('Check of pre-requisites'),
				'fnc' => 'stage1'
			],
			2 => [
				'title' => _('Configure DB connection'),
				'fnc' => 'stage2'
			],
			3 => [
				'title' => _('Zabbix server details'),
				'fnc' => 'stage3'
			],
			4 => [
				'title' => _('GUI settings'),
				'fnc' => 'stage4'
			],
			5 => [
				'title' => _('Pre-installation summary'),
				'fnc' => 'stage5'
			],
			6 => [
				'title' => _('Install'),
				'fnc' => 'stage6'
			]
		];

		$this->eventHandler();

		parent::__construct('post');
	}

	function getConfig($name, $default = null) {
		return CSession::keyExists($name) ? CSession::getValue($name) : $default;
	}

	function setConfig($name, $value) {
		CSession::setValue($name, $value);
	}

	function getStep() {
		return $this->getConfig('step', 0);
	}

	function doNext() {
		if (isset($this->stage[$this->getStep() + 1])) {
			$this->setConfig('step', $this->getStep('step') + 1);

			return true;
		}

		return false;
	}

	function doBack() {
		if (isset($this->stage[$this->getStep() - 1])) {
			$this->setConfig('step', $this->getStep('step') - 1);

			return true;
		}

		return false;
	}

	protected function bodyToString($destroy = true) {
		$setup_left = (new CDiv())
			->addClass(ZBX_STYLE_SETUP_LEFT)
			->addItem((new CDiv(makeLogo(LOGO_TYPE_NORMAL)))->addClass('setup-logo'))
			->addItem($this->getList());

		$setup_right = (new CDiv($this->getStage()))->addClass(ZBX_STYLE_SETUP_RIGHT);

		if (CWebUser::$data && CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			$cancel_button = (new CSubmit('cancel', _('Cancel')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass(ZBX_STYLE_FLOAT_LEFT);
			if ($this->DISABLE_CANCEL_BUTTON) {
				$cancel_button->setEnabled(false);
			}
		}
		else {
			$cancel_button = null;
		}

		if (array_key_exists($this->getStep() + 1, $this->stage)) {
			$next_button = new CSubmit('next['.$this->getStep().']', _('Next step'));
		}
		else {
			$next_button = new CSubmit($this->SHOW_RETRY_BUTTON ? 'retry' : 'finish', _('Finish'));
		}

		$back_button = (new CSubmit('back['.$this->getStep().']', _('Back')))
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(ZBX_STYLE_FLOAT_LEFT);

		if ($this->getStep() == 0 || $this->DISABLE_BACK_BUTTON) {
			$back_button->setEnabled(false);
		}

		$setup_footer = (new CDiv([new CDiv([$next_button, $back_button]), $cancel_button]))
			->addClass(ZBX_STYLE_SETUP_FOOTER);

		$setup_container = (new CDiv([$setup_left, $setup_right, $setup_footer]))->addClass(ZBX_STYLE_SETUP_CONTAINER);

		return parent::bodyToString($destroy).$setup_container->toString();
	}

	function getList() {
		$list = new CList();

		foreach ($this->stage as $id => $data) {
			$list->addItem($data['title'], ($id <= $this->getStep()) ? ZBX_STYLE_SETUP_LEFT_CURRENT : null);
		}

		return $list;
	}

	function getStage() {
		$function = $this->stage[$this->getStep()]['fnc'];
		return $this->$function();
	}

	function stage0() {
		preg_match('/^\d+\.\d+/', ZABBIX_VERSION, $version);
		$setup_title = (new CDiv([new CSpan(_('Welcome to')), 'Zabbix '.$version[0]]))->addClass(ZBX_STYLE_SETUP_TITLE);

		$default_lang = $this->getConfig('default_lang');
		$lang_combobox = (new CComboBox('default_lang', $default_lang, 'submit();'))
			->setAttribute('autofocus', 'autofocus');

		$all_locales_available = 1;

		foreach (getLocales() as $localeid => $locale) {
			if (!$locale['display']) {
				continue;
			}

			/*
			 * Checking if this locale exists in the system. The only way of doing it is to try and set one
			 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
			 */
			$locale_available = ($localeid === ZBX_DEFAULT_LANG
					|| setlocale(LC_MONETARY, zbx_locale_variants($localeid))
			);

			$lang_combobox->addItem($localeid, $locale['name'], null, $locale_available);

			$all_locales_available &= (int) $locale_available;
		}

		// Restoring original locale.
		setlocale(LC_MONETARY, zbx_locale_variants($default_lang));

		$language_error = '';
		if (!function_exists('bindtextdomain')) {
			$language_error = 'Translations are unavailable because the PHP gettext module is missing.';
			$lang_combobox->setEnabled(false);
		}
		elseif ($all_locales_available == 0) {
			$language_error = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
		}

		$language_select = (new CFormList())
			->addRow(_('Default language'),
				($language_error !== '')
					? [$lang_combobox, (makeErrorIcon($language_error))->addStyle('margin-left: 5px;')]
					: $lang_combobox
			);

		return (new CDiv([$setup_title, $language_select]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY);
	}

	function stage1() {
		$table = (new CTable())
			->addClass(ZBX_STYLE_LIST_TABLE)
			->setHeader(['', _('Current value'), _('Required'), '']);

		$messages = [];
		$finalResult = CFrontendSetup::CHECK_OK;

		foreach ($this->frontendSetup->checkRequirements() as $req) {
			if ($req['result'] == CFrontendSetup::CHECK_OK) {
				$class = ZBX_STYLE_GREEN;
				$result = 'OK';
			}
			elseif ($req['result'] == CFrontendSetup::CHECK_WARNING) {
				$class = ZBX_STYLE_ORANGE;
				$result = new CSpan(_x('Warning', 'setup'));
			}
			else {
				$class = ZBX_STYLE_RED;
				$result = new CSpan(_('Fail'));
				$messages[] = ['type' => 'error', 'message' => $req['error']];
			}

			$table->addRow(
				[
					$req['name'],
					$req['current'],
					($req['required'] !== null) ? $req['required'] : '',
					(new CCol($result))->addClass($class)
				]
			);

			if ($req['result'] > $finalResult) {
				$finalResult = $req['result'];
			}
		}

		if ($finalResult == CFrontendSetup::CHECK_FATAL) {
			$message_box = makeMessageBox(false, $messages, null, false, true);
		}
		else {
			$message_box = null;
		}

		return [
			new CTag('h1', true, _('Check of pre-requisites')),
			(new CDiv([$message_box, $table]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage2() {
		$DB['TYPE'] = $this->getConfig('DB_TYPE');
		$DB['CREDS_STORAGE'] = (int) $this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG);

		$table = (new CFormList())
			->addVar('tls_encryption', '0', 'tls_encryption_off')
			->addVar('verify_host', '0', 'verify_host_off');

		$table->addRow(_('Database type'),
			new CComboBox('type', $DB['TYPE'], 'submit()', CFrontendSetup::getSupportedDatabases())
		);

		$table->addRow(_('Database host'),
			(new CTextBox('server', $this->getConfig('DB_SERVER', 'localhost')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow(_('Database port'), [
			(new CNumericBox('port', $this->getConfig('DB_PORT', '0'), 5, false, false, false))
				->removeAttribute('style')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSpan(_('0 - use default port')))->addClass(ZBX_STYLE_GREY)
		]);

		$table->addRow(_('Database name'),
			(new CTextBox('database', $this->getConfig('DB_DATABASE', 'zabbix')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		if ($DB['TYPE'] == ZBX_DB_POSTGRESQL) {
			$table->addRow(_('Database schema'),
				(new CTextBox('schema', $this->getConfig('DB_SCHEMA', '')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
		}

		$table->addRow(_('Store credentials in'), [
			(new CRadioButtonList('creds_storage', $DB['CREDS_STORAGE']))
				->addValue(_('Plain text'), DB_STORE_CREDS_CONFIG, null, 'submit()')
				->addValue(_('HashiCorp Vault'), DB_STORE_CREDS_VAULT, null, 'submit()')
				->setModern(true)
		]);

		if ($DB['CREDS_STORAGE'] == DB_STORE_CREDS_VAULT) {
			$table
				->addRow(_('Vault API endpoint'), 
					(new CTextBox('vault_host', $this->getConfig('DB_VAULT_HOST') ? : self::VAULT_HOST_DEFAULT)) // TODO
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
				->addRow(_('Vault secret path'),
					(new CTextBox('vault_secret', $this->getConfig('DB_VAULT_SECRET')))
						->setAttribute('placeholder', 'path/to/secret')
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addRow(_('Vault authentication token'),
					(new CTextBox('vault_token', $this->getConfig('DB_VAULT_TOKEN')))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addVar('user', '')
				->addVar('password', '');
		}
		else {
			$table
				->addRow(_('User'),
					(new CTextBox('user', $this->getConfig('DB_USER', 'zabbix')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addRow(_('Password'),
					(new CPassBox('password', $this->getConfig('DB_PASSWORD')))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addVar('vault_host', '')
				->addVar('vault_secret', '')
				->addVar('vault_token', '');
		}

		If ($DB['TYPE'] === null || $DB['TYPE'] == ZBX_DB_MYSQL || $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
			$table->addRow(_('TLS encryption'),
				(new CCheckBox('tls_encryption'))
					->setChecked($this->getConfig('DB_ENCRYPTION'))
					->onChange('submit()')
			);
			$show_tls = true;
		}
		else {
			$show_tls = false;
		}

		if ($show_tls && $this->getConfig('DB_ENCRYPTION')) {
			$table->addRow(_('TLS key file'),
				(new CTextBox('key_file', $this->getConfig('DB_KEY_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			);

			$table->addRow(_('TLS certificate file'),
				(new CTextBox('cert_file', $this->getConfig('DB_CERT_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			);

			$table->addRow(_('TLS certificate authority file'),
				(new CTextBox('ca_file', $this->getConfig('DB_CA_FILE')))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			);

			$verify_host_box = new CCheckBox('verify_host');

			if ($DB['TYPE'] == ZBX_DB_MYSQL) {
				$verify_host_box
					->setChecked(true)
					->setAttribute('readonly', true);
			}
			else {
				$verify_host_box->setChecked($this->getConfig('DB_VERIFY_HOST'));
			}

			$table->addRow(_('With host verification'), $verify_host_box);

			If ($DB['TYPE'] == ZBX_DB_MYSQL) {
				$table->addRow(_('TLS cipher list'),
					(new CTextBox('cipher_list', $this->getConfig('DB_CIPHER_LIST')))
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				);
			}
		}
		else {
			$table
				->addVar('key_file', '')
				->addVar('cert_file', '')
				->addVar('ca_file', '')
				->addVar('cipher_list', '');
		}

		if ($this->STEP_FAILED) {
			global $ZBX_MESSAGES;

			$message_box = makeMessageBox(false, $ZBX_MESSAGES, _('Cannot connect to the database.'), false, true);
		}
		else {
			$message_box = null;
		}

		return [
			new CTag('h1', true, _('Configure DB connection')),
			(new CDiv([
				new CTag('p', true, _s('Please create database manually, and set the configuration parameters for connection to this database. Press "%1$s" button when done.', _('Next step'))),
				$message_box,
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function stage3() {
		$table = new CFormList();

		$table->addRow(_('Host'),
			(new CTextBox('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow(_('Port'),
			(new CNumericBox('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051'), 5, false, false, false))
				->removeAttribute('style')
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		$table->addRow('Name',
			(new CTextBox('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		);

		return [
			new CTag('h1', true, _('Zabbix server details')),
			(new CDiv([
				new CTag('p', true, _('Please enter the host name or host IP address and port number of the Zabbix server, as well as the name of the installation (optional).')),
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	protected function stage4(): array {
		$timezones = DateTimeZone::listIdentifiers();

		$table = (new CFormList())
			->addRow(_('Default time zone'),
				new CComboBox('default_timezone', $this->getConfig('default_timezone'), null,
					[ZBX_DEFAULT_TIMEZONE => _('System')] + array_combine($timezones, $timezones)
				)
			)
			->addRow(_('Default theme'),
				new CComboBox('default_theme', $this->getConfig('default_theme'), 'submit()', APP::getThemes())
			);

		return [
			new CTag('h1', true, _('GUI settings')),
			(new CDiv($table))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	protected function stage5(): array {
		$db_type = $this->getConfig('DB_TYPE');
		$databases = CFrontendSetup::getSupportedDatabases();

		$table = new CFormList();
		$table->addRow((new CSpan(_('Database type')))->addClass(ZBX_STYLE_GREY), $databases[$db_type]);

		$db_port = ($this->getConfig('DB_PORT') == 0) ? _('default') : $this->getConfig('DB_PORT');
		$db_password = preg_replace('/./', '*', $this->getConfig('DB_PASSWORD'));

		$table->addRow((new CSpan(_('Database server')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_SERVER'));
		$table->addRow((new CSpan(_('Database port')))->addClass(ZBX_STYLE_GREY), $db_port);
		$table->addRow((new CSpan(_('Database name')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_DATABASE'));
		$table->addRow((new CSpan(_('Database user')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_USER'));
		$table->addRow((new CSpan(_('Database password')))->addClass(ZBX_STYLE_GREY), $db_password);
		if ($db_type == ZBX_DB_POSTGRESQL) {
			$table->addRow((new CSpan(_('Database schema')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_SCHEMA'));
		}
		$table->addRow((new CSpan(_('TLS encryption')))->addClass(ZBX_STYLE_GREY),
			$this->getConfig('DB_ENCRYPTION') ? 'true' : 'false');
		if ($this->getConfig('DB_ENCRYPTION')) {
			$table->addRow((new CSpan(_('TLS key file')))->addClass(ZBX_STYLE_GREY), $this->getConfig('DB_KEY_FILE'));
			$table->addRow((new CSpan(_('TLS certificate file')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_CERT_FILE')
			);
			$table->addRow((new CSpan(_('TLS certificate authority file')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_CA_FILE')
			);
			$table->addRow((new CSpan(_('With host verification')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_VERIFY_HOST') ? 'true' : 'false'
			);
			$table->addRow((new CSpan(_('TLS cipher list')))->addClass(ZBX_STYLE_GREY),
				$this->getConfig('DB_CIPHER_LIST')
			);
		}

		$table->addRow(null, null);

		$table->addRow((new CSpan(_('Zabbix server')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER'));
		$table->addRow((new CSpan(_('Zabbix server port')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER_PORT'));
		$table->addRow((new CSpan(_('Zabbix server name')))->addClass(ZBX_STYLE_GREY), $this->getConfig('ZBX_SERVER_NAME'));

		return [
			new CTag('h1', true, _('Pre-installation summary')),
			(new CDiv([
				new CTag('p', true, _s('Please check configuration parameters. If all is correct, press "%1$s" button, or "%2$s" button to change configuration parameters.', _('Next step'), _('Back'))),
				$table
			]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	protected function stage6(): array {
		$vault_config = [
			'VAULT_HOST' => '',
			'VAULT_SECRET' => '',
			'VAULT_TOKEN' => ''
		];

		$db_creds_config = [
			'USER' => '',
			'PASSWORD' => ''
		];

		if ($this->getConfig('DB_CREDS_STORAGE') == DB_STORE_CREDS_VAULT) {
			$vault_config['VAULT_HOST'] = $this->getConfig('DB_VAULT_HOST');
			$vault_config['VAULT_SECRET'] = $this->getConfig('DB_VAULT_SECRET');
			$vault_config['VAULT_TOKEN'] = $this->getConfig('DB_VAULT_TOKEN');

			$vault = new CVaultHelper($vault_config['VAULT_HOST'], $vault_config['VAULT_TOKEN']);
			$secret = $vault->loadSecret($vault_config['VAULT_SECRET']);

			if (array_key_exists('username', $secret) && array_key_exists('password', $secret)) {
				$this->dbConnect($secret['username'], $secret['password']);
			}
			else {
				return false;
			}
		}
		else {
			$db_creds_config['USER'] = $this->getConfig('DB_USER');
			$db_creds_config['PASSWORD'] = $this->getConfig('DB_PASSWORD');

			$this->dbConnect();
		}

		$update = [];
		foreach (['default_lang', 'default_timezone', 'default_theme'] as $key) {
			$update[] = $key.'='.zbx_dbstr($this->getConfig($key));
		}
		DBexecute('UPDATE config SET '.implode(',', $update));
		$this->dbClose();

		$this->setConfig('ZBX_CONFIG_FILE_CORRECT', true);

		$config_file_name = APP::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH;
		$config = new CConfigFile($config_file_name);
		$config->config = [
			'DB' => [
				'TYPE' => $this->getConfig('DB_TYPE'),
				'SERVER' => $this->getConfig('DB_SERVER'),
				'PORT' => $this->getConfig('DB_PORT'),
				'DATABASE' => $this->getConfig('DB_DATABASE'),
				'SCHEMA' => $this->getConfig('DB_SCHEMA'),
				'ENCRYPTION' => $this->getConfig('DB_ENCRYPTION'),
				'KEY_FILE' => $this->getConfig('DB_KEY_FILE'),
				'CERT_FILE' => $this->getConfig('DB_CERT_FILE'),
				'CA_FILE' => $this->getConfig('DB_CA_FILE'),
				'VERIFY_HOST' => $this->getConfig('DB_VERIFY_HOST'),
				'CIPHER_LIST' => $this->getConfig('DB_CIPHER_LIST'),
				'DOUBLE_IEEE754' => $this->getConfig('DB_DOUBLE_IEEE754')
			] + $db_creds_config + $vault_config,
			'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
			'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
			'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
		];

		$error = false;

		if (!$config->save()) {
			$error = true;
			$messages[] = [
				'type' => 'error',
				'message' => $config->error
			];
		}

		if ($error) {
			$this->SHOW_RETRY_BUTTON = true;

			$this->setConfig('ZBX_CONFIG_FILE_CORRECT', false);

			$message_box = makeMessageBox(false, $messages, _('Cannot create the configuration file.'), false, true);
			$message = [
				new CTag('p', true, _('Alternatively, you can install it manually:')),
				new CTag('ol', true, [
					new CTag('li', true, new CLink(_('Download the configuration file'), 'setup.php?save_config=1')),
					new CTag('li', true, _s('Save it as "%1$s"', $config_file_name))
				]),
			];
		}
		else {
			$this->DISABLE_CANCEL_BUTTON = true;
			$this->DISABLE_BACK_BUTTON = true;

			$message_box = null;
			$message = [
				(new CTag('h1', true, _('Congratulations! You have successfully installed Zabbix frontend.')))
					->addClass(ZBX_STYLE_GREEN),
				new CTag('p', true, _s('Configuration file "%1$s" created.', $config_file_name))
			];
		}

		return [
			new CTag('h1', true, _('Install')),
			(new CDiv([$message_box, $message]))->addClass(ZBX_STYLE_SETUP_RIGHT_BODY)
		];
	}

	function dbConnect(?string $username = null, ?string $password = null) {
		global $DB;

		if (!$this->getConfig('check_fields_result')) {
			return false;
		}

		$DB['TYPE'] = $this->getConfig('DB_TYPE');
		if (is_null($DB['TYPE'])) {
			return false;
		}

		$DB['SERVER'] = $this->getConfig('DB_SERVER', 'localhost');
		$DB['PORT'] = $this->getConfig('DB_PORT', '0');
		$DB['DATABASE'] = $this->getConfig('DB_DATABASE', 'zabbix');
		$DB['USER'] = $username ? : $this->getConfig('DB_USER', 'root');
		$DB['PASSWORD'] = $password ? : $this->getConfig('DB_PASSWORD', '');
		$DB['SCHEMA'] = $this->getConfig('DB_SCHEMA', '');
		$DB['ENCRYPTION'] = (bool) $this->getConfig('DB_ENCRYPTION', true);
		$DB['VERIFY_HOST'] = (bool) $this->getConfig('DB_VERIFY_HOST', true);
		$DB['KEY_FILE'] = $this->getConfig('DB_KEY_FILE', '');
		$DB['CERT_FILE'] = $this->getConfig('DB_CERT_FILE', '');
		$DB['CA_FILE'] = $this->getConfig('DB_CA_FILE', '');
		$DB['CIPHER_LIST'] = $this->getConfig('DB_CIPHER_LIST', '');

		$error = '';

		// During setup set debug to false to avoid displaying unwanted PHP errors in messages.
		if (DBconnect($error)) {
			return true;
		}
		else {
			return $error;
		}
	}

	function dbClose() {
		global $DB;

		DBclose();

		$DB = null;
	}

	function checkConnection() {
		global $DB;

		$result = true;

		if (!zbx_empty($DB['SCHEMA']) && $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
			$db_schema = DBselect(
				"SELECT schema_name".
				" FROM information_schema.schemata".
				" WHERE schema_name='".pg_escape_string($DB['SCHEMA'])."'"
			);
			$result = DBfetch($db_schema);
		}

		$db = DB::getDbBackend();

		if (!$db->checkEncoding()) {
			error($db->getWarning());

			return false;
		}

		return $result;
	}

	function eventHandler() {
		if (hasRequest('back') && array_key_exists($this->getStep(), getRequest('back'))) {
			$this->doBack();
		}

		if ($this->getStep() == 1) {
			if (hasRequest('next') && array_key_exists(1, getRequest('next'))) {
				$finalResult = CFrontendSetup::CHECK_OK;
				foreach ($this->frontendSetup->checkRequirements() as $req) {
					if ($req['result'] > $finalResult) {
						$finalResult = $req['result'];
					}
				}

				if ($finalResult == CFrontendSetup::CHECK_FATAL) {
					$this->STEP_FAILED = true;
					unset($_REQUEST['next']);
				}
				else {
					$this->doNext();
				}
			}
		}
		elseif ($this->getStep() == 2) {
			$this->setConfig('DB_TYPE', getRequest('type', $this->getConfig('DB_TYPE')));
			$this->setConfig('DB_SERVER', getRequest('server', $this->getConfig('DB_SERVER', 'localhost')));
			$this->setConfig('DB_PORT', getRequest('port', $this->getConfig('DB_PORT', '0')));
			$this->setConfig('DB_DATABASE', getRequest('database', $this->getConfig('DB_DATABASE', 'zabbix')));
			$this->setConfig('DB_SCHEMA', getRequest('schema', $this->getConfig('DB_SCHEMA', '')));
			$this->setConfig('DB_ENCRYPTION',
				getRequest('tls_encryption', $this->getConfig('DB_ENCRYPTION', true))
			);
			$this->setConfig('DB_VERIFY_HOST',
				getRequest('verify_host', $this->getConfig('DB_VERIFY_HOST', true))
			);
			$this->setConfig('DB_KEY_FILE', getRequest('key_file', $this->getConfig('DB_KEY_FILE', '')));
			$this->setConfig('DB_CERT_FILE', getRequest('cert_file', $this->getConfig('DB_CERT_FILE', '')));
			$this->setConfig('DB_CA_FILE', getRequest('ca_file', $this->getConfig('DB_CA_FILE', '')));
			$this->setConfig('DB_CIPHER_LIST', getRequest('cipher_list', $this->getConfig('DB_CIPHER_LIST', '')));

			$creds_storage = getRequest('creds_storage', $this->getConfig('DB_CREDS_STORAGE', DB_STORE_CREDS_CONFIG));
			$this->setConfig('DB_CREDS_STORAGE', $creds_storage);

			switch ($creds_storage) {
				case DB_STORE_CREDS_CONFIG:
					$this->setConfig('DB_USER', getRequest('user', $this->getConfig('DB_USER', 'root')));
					$this->setConfig('DB_PASSWORD', getRequest('password', $this->getConfig('DB_PASSWORD', '')));
					$this->setConfig('DB_VAULT_HOST', '');
					$this->setConfig('DB_VAULT_SECRET', '');
					$this->setConfig('DB_VAULT_TOKEN', '');
					break;

				case DB_STORE_CREDS_VAULT:
					$vault_host = getRequest('vault_host', $this->getConfig('DB_VAULT_HOST', self::VAULT_HOST_DEFAULT));
					$vault_secret = getRequest('vault_secret', $this->getConfig('DB_VAULT_SECRET'));
					$vault_token = getRequest('vault_token', $this->getConfig('DB_VAULT_TOKEN'));

					$this->setConfig('DB_VAULT_HOST', $vault_host);
					$this->setConfig('DB_VAULT_SECRET', $vault_secret);
					$this->setConfig('DB_VAULT_TOKEN', $vault_token);
					$this->setConfig('DB_USER', '');
					$this->setConfig('DB_PASSWORD', '');
					break;
			}

			if (hasRequest('next') && array_key_exists(2, getRequest('next'))) {
				if ($creds_storage == DB_STORE_CREDS_VAULT) {
					$vault_connection_cheched = false;
					$secret_parser = new CVaultSecretParser(['with_key' => false]);
					$secret = [];

					if (ini_get('allow_url_fopen') != 1) {
						$db_connected = _('Please enable "allow_url_fopen" directive.');
					}
					elseif (CVaultHelper::validateVaultApiEndpoint($vault_host)
							&& CVaultHelper::validateVaultToken($vault_token)
							&& $secret_parser->parse($vault_secret) == CParser::PARSE_SUCCESS) {
						$vault = new CVaultHelper($vault_host, $vault_token);
						$secret = $vault->loadSecret($vault_secret);

						if ($secret) {
							$vault_connection_cheched = true;
						}
					}

					if (!$vault_connection_cheched) {
						$db_connected = _('Vault connection failed.');
					}
					elseif (!array_key_exists('username', $secret)
							|| !array_key_exists('password', $secret)) {
						$db_connected = _('Username and password must be stored in Vault secret keys "username" and "password".');
					}
					else {
						$db_connected = $this->dbConnect($secret['username'], $secret['password']);
					}
				}
				else {
					$db_connected = $this->dbConnect();
				}

				if ($db_connected === true) {
					$db_connection_checked = $this->checkConnection();
				}
				else {
					error($db_connected);
					$db_connection_checked = false;
				}

				if ($db_connection_checked) {
					$this->setConfig('DB_DOUBLE_IEEE754', DB::getDbBackend()->isDoubleIEEE754());
				}

				if ($db_connected === true) {
					$this->dbClose();
				}

				if ($db_connection_checked) {
					$this->doNext();
				}
				else {
					$this->STEP_FAILED = true;
					unset($_REQUEST['next']);
				}
			}
		}
		elseif ($this->getStep() == 3) {
			$this->setConfig('ZBX_SERVER', getRequest('zbx_server', $this->getConfig('ZBX_SERVER', 'localhost')));
			$this->setConfig('ZBX_SERVER_PORT', getRequest('zbx_server_port', $this->getConfig('ZBX_SERVER_PORT', '10051')));
			$this->setConfig('ZBX_SERVER_NAME', getRequest('zbx_server_name', $this->getConfig('ZBX_SERVER_NAME', '')));

			if (hasRequest('next') && array_key_exists(3, getRequest('next'))) {
				$this->doNext();
			}
		}
		elseif ($this->getStep() == 6) {
			if (hasRequest('save_config')) {
				$vault_config = [
					'VAULT_HOST' => '',
					'VAULT_SECRET' => '',
					'VAULT_TOKEN' => ''
				];

				$db_creds_config = [
					'USER' => '',
					'PASSWORD' => ''
				];

				if ($this->getConfig('DB_CREDS_STORAGE') == DB_STORE_CREDS_VAULT) {
					$vault_config['VAULT_HOST'] = $this->getConfig('DB_VAULT_HOST');
					$vault_config['VAULT_SECRET'] = $this->getConfig('DB_VAULT_SECRET');
					$vault_config['VAULT_TOKEN'] = $this->getConfig('DB_VAULT_TOKEN');
				}
				else {
					$db_creds_config['USER'] = $this->getConfig('DB_USER');
					$db_creds_config['PASSWORD'] = $this->getConfig('DB_PASSWORD');
				}

				// make zabbix.conf.php downloadable
				header('Content-Type: application/x-httpd-php');
				header('Content-Disposition: attachment; filename="'.basename(CConfigFile::CONFIG_FILE_PATH).'"');
				$config = new CConfigFile(APP::getInstance()->getRootDir().CConfigFile::CONFIG_FILE_PATH);
				$config->config = [
					'DB' => [
						'TYPE' => $this->getConfig('DB_TYPE'),
						'SERVER' => $this->getConfig('DB_SERVER'),
						'PORT' => $this->getConfig('DB_PORT'),
						'DATABASE' => $this->getConfig('DB_DATABASE'),
						'SCHEMA' => $this->getConfig('DB_SCHEMA'),
						'ENCRYPTION' => (bool) $this->getConfig('DB_ENCRYPTION'),
						'VERIFY_HOST' => (bool) $this->getConfig('DB_VERIFY_HOST'),
						'KEY_FILE' => $this->getConfig('DB_KEY_FILE'),
						'CERT_FILE' => $this->getConfig('DB_CERT_FILE'),
						'CA_FILE' => $this->getConfig('DB_CA_FILE'),
						'CIPHER_LIST' => $this->getConfig('DB_CIPHER_LIST'),
						'DOUBLE_IEEE754' => $this->getConfig('DB_DOUBLE_IEEE754')
					] + $db_creds_config + $vault_config,
					'ZBX_SERVER' => $this->getConfig('ZBX_SERVER'),
					'ZBX_SERVER_PORT' => $this->getConfig('ZBX_SERVER_PORT'),
					'ZBX_SERVER_NAME' => $this->getConfig('ZBX_SERVER_NAME')
				];
				die($config->getString());
			}
		}

		if (hasRequest('next') && array_key_exists($this->getStep(), getRequest('next'))) {
			$this->doNext();
		}
	}
}
