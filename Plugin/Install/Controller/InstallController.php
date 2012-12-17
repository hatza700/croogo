<?php

App::uses('File', 'Utility');
App::uses('InstallManager','Install.Lib');
/**
 * Install Controller
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Croogo
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class InstallController extends Controller {

/**
 * No components required
 *
 * @var array
 * @access public
 */
	public $components = array('Session');

/**
 * Helpers
 *
 * @var array
 * @access public
 */
	public $helpers = array(
		'Html' => array(
			'className' => 'CroogoHtml',
		),
		'Form' => array(
			'className' => 'CroogoForm',
		),
		'Layout',
	);

/**
 * beforeFilter
 *
 * @return void
 * @access public
 */
	public function beforeFilter() {
		parent::beforeFilter();

		$this->layout = 'install';
		$this->_generateAssets();
	}

/**
 * Generate assets
 */
	protected function _generateAssets() {
		if (!file_exists(CSS . 'croogo-bootstrap.css')) {
			App::uses('AssetGenerator', 'Install.Lib');
			$generator = new AssetGenerator();
			try {
				$generator->generate();
			} catch (Exception $e) {
				$this->log($e->getMessage());
				$this->Session->setFlash('Asset generation failed. Please verify that dependencies exists and readable.', 'default', array('class' => 'error'));
			}
		}
	}

/**
 * If settings.json exists, app is already installed
 *
 * @return void
 */
	protected function _check() {
		if (Configure::read('Install.installed') && Configure::read('Install.secured')) {
			$this->Session->setFlash('Already Installed');
			$this->redirect('/');
		}
	}

/**
 * Step 0: welcome
 *
 * A simple welcome message for the installer.
 *
 * @return void
 * @access public
 */
	public function index() {
		$this->_check();
		$this->set('title_for_layout', __('Installation: Welcome'));
	}

/**
 * Step 1: database
 *
 * Try to connect to the database and give a message if that's not possible so the user can check their
 * credentials or create the missing database
 * Create the database file and insert the submitted details
 *
 * @return void
 * @access public
 */
	public function database() {
		$this->_check();
		$this->set('title_for_layout', __('Step 1: Database'));

		if (Configure::read('Install.installed')) {
			$this->redirect(array('action' => 'adminuser'));
		}

		if (!empty($this->request->data)) {
			$InstallManager = new InstallManager();
			$result = $InstallManager->createDatabaseFile($this->request->data);
			if ($result !== true) {
				$this->Session->setFlash($result, 'default', array('class' => 'error'));
			} else {
				$this->redirect(array('action' => 'data'));
			}
		}
	}

/**
 * Step 2: Run the initial sql scripts to create the db and seed it with data
 *
 * @return void
 * @access public
 */
	public function data() {
		$this->_check();
		$this->set('title_for_layout', __('Step 2: Build database'));
		if (isset($this->params['named']['run'])) {
			$this->loadModel('Install.Install');
			$this->Install->setupDatabase();
			
			$InstallManager = new InstallManager();
			$result = $InstallManager->createCroogoFile();

			$this->redirect(array('action' => 'adminuser'));
		}
	}

/**
 * Step 3: get username and passwords for administrative user
 */
	public function adminuser() {
		if (!file_exists(APP . 'Config' . DS . 'database.php')) {
			$this->redirect('/');
		}

		if ($this->request->is('post')) {
			if (!CakePlugin::loaded('Users')) {
				CakePlugin::load('Users');
			}
			$this->loadModel('Users.User');
			$this->User->set($this->request->data);
			if ($this->User->validates()) {
				if ($this->Install->addAdminUser($this->request->data)) {
					$this->redirect(array('action' => 'finish'));
				}
			}
		}
	}

/**
 * Step 4: finish
 *
 * Copy settings.json file into place and create user obtained in step 3
 *
 * @return void
 * @access public
 */
	public function finish($token = null) {
		$this->set('title_for_layout', __('Installation successful'));
		$this->_check();

		$InstallManager = new InstallManager();
		$result = $InstallManager->createSettingsFile();
		
		$urlBlogAdd = Router::url(array(
			'plugin' => 'nodes',
			'admin' => true,
			'controller' => 'nodes',
			'action' => 'add',
			'blog',
		));
		$urlSettings = Router::url(array(
			'plugin' => 'settings',
			'admin' => true,
			'controller' => 'settings',
			'action' => 'prefix',
			'Site',
		));

		$this->set('user', $install);
		$this->set(compact('urlBlogAdd', 'urlSettings'));
	}

}
