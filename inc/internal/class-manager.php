<?php

namespace Courier\Internal;

use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\IO\NullIO;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\RootPackageInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryManager;
use WP_Error;

class Manager {
	/**
	 * Singleton instance of the Manager
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Plugins registered with the Manager
	 *
	 * @var array
	 */
	protected $plugins = array();

	/**
	 * Plugins available on the current site
	 *
	 * @var array
	 */
	protected $available = array();

	/**
	 * Get the current singleton instance of the manager
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( empty( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	protected static function getHomeDir() {
		$home = getenv('COMPOSER_HOME');
		if (!$home) {
			if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
				if (!getenv('APPDATA')) {
					throw new \RuntimeException('The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly');
				}
				$home = strtr(getenv('APPDATA'), '\\', '/') . '/Composer';
			} else {
				if (!getenv('HOME')) {
					throw new \RuntimeException('The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly');
				}
				$home = rtrim(getenv('HOME'), '/') . '/.composer';
			}
		}

		return $home;
	}

	/**
	 * @return string
	 */
	protected static function getCacheDir($home)
	{
		$cacheDir = getenv('COMPOSER_CACHE_DIR');
		if (!$cacheDir) {
			if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
				if ($cacheDir = getenv('LOCALAPPDATA')) {
					$cacheDir .= '/Composer';
				} else {
					$cacheDir = $home . '/cache';
				}
				$cacheDir = strtr($cacheDir, '\\', '/');
			} else {
				$cacheDir = $home.'/cache';
			}
		}

		return $cacheDir;
	}

	public function __construct() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$this->config = new Config();
		$this->repositoryManager = $rm = new RepositoryManager( new NullIO(), $this->config );
		$rm->setRepositoryClass('composer', 'Composer\Repository\ComposerRepository');
		$rm->setRepositoryClass('vcs', 'Composer\Repository\VcsRepository');
		$rm->setRepositoryClass('package', 'Composer\Repository\PackageRepository');
		$rm->setRepositoryClass('pear', 'Composer\Repository\PearRepository');
		$rm->setRepositoryClass('git', 'Composer\Repository\VcsRepository');
		$rm->setRepositoryClass('svn', 'Composer\Repository\VcsRepository');
		$rm->setRepositoryClass('perforce', 'Composer\Repository\VcsRepository');
		$rm->setRepositoryClass('hg', 'Composer\Repository\VcsRepository');
		$rm->setRepositoryClass('artifact', 'Composer\Repository\ArtifactRepository');

		$home = $this->getHomeDir();
		$cache = $this->getCacheDir( $home );
		$this->config->merge(array('config' => array('home' => $home, 'cache-dir' => $cache)));

		foreach ($this->config->getRepositories() as $index => $repo) {
			if (!is_array($repo)) {
				throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') should be an array, '.gettype($repo).' given');
			}
			if (!isset($repo['type'])) {
				throw new \UnexpectedValueException('Repository '.$index.' ('.json_encode($repo).') must have a type defined');
			}
			$name = is_int($index) && isset($repo['url']) ? preg_replace('{^https?://}i', '', $repo['url']) : $index;
			while (isset($repos[$name])) {
				$name .= '2';
			}
			$repo_object = $rm->createRepository( $repo['type'], $repo );
			$rm->addRepository( $repo_object );
		}
	}

	public function add() {
		$args = func_get_args();

		try {
			list( $id, $header ) = $this->get_plugin_from_trace( debug_backtrace() );

			$plugin = new Package\Plugin( $id, $header );

			if ( is_string( $args[0] ) ) {
				// Basic syntax
				$plugin->parse_basic_syntax( $args );
			}
			else {
				$plugin->parse_advanced_syntax( $args[0] );
			}
		}
		catch ( Exception $e ) {
			trigger_error( $e->getMessage(), E_USER_WARNING );
			return false;
		}

		$this->plugins[] = $plugin;

		return true;
	}

	protected static function get_plugin_from_trace( $trace ) {
		$file = null;

		while ( $elem = array_shift( $trace ) ) {
			if ( empty( $elem['file'] ) ) {
				// We catch this later
				continue;
			}

			// Is this from a Courier file?
			if ( strpos( $elem['file'], COURIER_BASE ) === 0 ) {
				continue;
			}

			$file = $elem['file'];
			break;
		}

		if ( empty( $file ) ) {
			throw new Exception( 'Plugin file could not be detected via trace' );
		}

		$available_plugins = get_plugins();
		$plugins_dir = trailingslashit( WP_PLUGIN_DIR );
		if ( strpos( $file, $plugins_dir ) === 0 ) {
			$file = substr( $file, strlen( $plugins_dir ) );
		}

		if ( empty( $available_plugins[ $file ] ) ) {
			throw new Exception( 'Invalid plugin file found via trace' );
		}

		if ( strpos( $file, '/' ) === false ) {
			throw new Exception( 'Only plugins in directories can require files' );
		}

		// Hackity hackity hack
		add_action( 'activate_' . $file, 'Courier\\Internal\\check_resolution', COURIER_RESOLUTION_PRIORITY );

		$id = basename( dirname( $file ) );

		return array( $id, $available_plugins[ $file ] );
	}

	public function get_available_repo() {
		if ( ! empty( $this->available ) ) {
			return $this->available;
		}

		$this->available = new InstalledArrayRepository();

		$available_plugins = get_plugins();
		foreach ( $available_plugins as $file => $header ) {
			if ( strpos( $file, '/' ) === false ) {
				continue;
			}

			// Convert to ID
			$id = basename( dirname( $file ) );

			$package = new Package\Plugin( $id, $header );
			$this->available->addPackage( $package );
		}

		return $this->available;
	}

	public function resolve() {
		$error = new WP_Error();
		$unresolvable = false;

		foreach ( $this->plugins as $plugin ) {
			try {
				$this->resolve_package( $plugin );
			}
			catch ( SolverProblemsException $e ) {
				if ( ! $unresolvable ) {
					$error->add(
						'unresolvable',
						'<strong>Your requirements could not be resolved to an installable set of packages.</strong>'
					);
					$unresolvable = true;
				}
				foreach ( $e->getProblems() as $problem ) {
					$message = trim( $problem->getPrettyString() );
					$message = ltrim( $message, '- ' );
					$error->add(
						'unresolvable_message',
						$message
					);
				}
			}
		}

		if ( $unresolvable ) {
			return $error;
		}

		return true;
	}

	public function resolve_package( RootPackageInterface $package ) {
		$policy = $this->create_policy( $package );
		$pool = $this->create_pool( $package, WP_DEBUG );

		$packages = array();
		$localRepo = new InstalledArrayRepository( $this->plugins );
		$platformRepo = new PlatformRepository();

		$repos = array(
			$localRepo,
			$platformRepo,
			$this->get_available_repo(),
		);
		$installedRepo = new CompositeRepository($repos);

		$pool->addRepository( $installedRepo );

		// $repositories = $this->repositoryManager->getRepositories();
		// foreach ($repositories as $repository) {
			// $pool->addRepository($repository);
		// }

		$request = $this->handle_request( $package, $pool, $localRepo, $platformRepo );


		// solve dependencies
		$solver = new Solver( $policy, $pool, $installedRepo );
		$operations = $solver->solve( $request );

		foreach ( $operations as $op ) {
			var_dump( $op->getJobType(), $op->getPackage() );
		}
		exit;
	}

	protected function create_policy( $package ) {
		return new DefaultPolicy( $package->getPreferStable() );
	}

	protected function create_pool( $package, $include_development ) {
		$minimumStability = $package->getMinimumStability();
		$stabilityFlags = $package->getStabilityFlags();

		$requires = $package->getRequires();
		if ( $include_development ) {
			$requires = array_merge( $requires, $package->getDevRequires() );
		}
		$rootConstraints = array();
		foreach ( $requires as $req => $constraint ) {
			$rootConstraints[$req] = $constraint->getConstraint();
		}

		return new Pool( $minimumStability, $stabilityFlags, $rootConstraints );
	}

	protected function handle_request( $package, Pool $pool, $localRepo, $platformRepo ) {
		// creating requirements request
		$request = $this->createRequest( $pool, $package, $platformRepo );

		// remove unstable packages from the localRepo if they don't match the current stability settings
		$removedUnstablePackages = array();
		foreach ($localRepo->getPackages() as $subpackage) {
			if (
				!$pool->isPackageAcceptable($subpackage->getNames(), $subpackage->getStability())
				&& $this->installationManager->isPackageInstalled($localRepo, $subpackage)
			) {
				$removedUnstablePackages[$subpackage->getName()] = true;
				$request->remove($subpackage->getName(), new VersionConstraint('=', $subpackage->getVersion()));
			}
		}

		if (false && $this->update) {
			$this->io->write('<info>Updating dependencies'.($withDevReqs?' (including require-dev)':'').'</info>');

			$request->updateAll();

			if ($withDevReqs) {
				$links = array_merge($package->getRequires(), $package->getDevRequires());
			} else {
				$links = $package->getRequires();
			}

			foreach ($links as $link) {
				$request->install($link->getTarget(), $link->getConstraint());
			}

			// if the updateWhitelist is enabled, packages not in it are also fixed
			// to the version specified in the lock, or their currently installed version
			if ($this->updateWhitelist) {
				$currentPackages = $installedRepo->getPackages();

				// collect packages to fixate from root requirements as well as installed packages
				$candidates = array();
				foreach ($links as $link) {
					$candidates[$link->getTarget()] = true;
				}
				foreach ($localRepo->getPackages() as $package) {
					$candidates[$package->getName()] = true;
				}

				// fix them to the version in lock (or currently installed) if they are not updateable
				foreach ($candidates as $candidate => $dummy) {
					foreach ($currentPackages as $curPackage) {
						if ($curPackage->getName() === $candidate) {
							if (!$this->isUpdateable($curPackage) && !isset($removedUnstablePackages[$curPackage->getName()])) {
								$constraint = new VersionConstraint('=', $curPackage->getVersion());
								$request->install($curPackage->getName(), $constraint);
							}
							break;
						}
					}
				}
			}
		} else {
			// $this->io->write('<info>Installing dependencies'.($withDevReqs?' (including require-dev)':'').'</info>');

			if ( WP_DEBUG ) {
				$links = array_merge($package->getRequires(), $package->getDevRequires());
			} else {
				$links = $package->getRequires();
			}

			foreach ($links as $link) {
				$request->install($link->getTarget(), $link->getConstraint());
			}
		}

		return $request;
	}
	
	protected function createRequest( Pool $pool, RootPackageInterface $rootPackage, PlatformRepository $platformRepo ) {
		$request = new Request( $pool );

		$constraint = new VersionConstraint('=', $rootPackage->getVersion());
		$constraint->setPrettyString($rootPackage->getPrettyVersion());
		$request->install($rootPackage->getName(), $constraint);

		$fixedPackages = $platformRepo->getPackages();
		/*if ($this->additionalInstalledRepository) {
			$additionalFixedPackages = $this->additionalInstalledRepository->getPackages();
			$fixedPackages = array_merge($fixedPackages, $additionalFixedPackages);
		}*/

		// fix the version of all platform packages + additionally installed packages
		// to prevent the solver trying to remove or update those
		$provided = $rootPackage->getProvides();
		foreach ($fixedPackages as $package) {
			$constraint = new VersionConstraint('=', $package->getVersion());
			$constraint->setPrettyString($package->getPrettyVersion());

			// skip platform packages that are provided by the root package
			if ($package->getRepository() !== $platformRepo
				|| !isset($provided[$package->getName()])
				|| !$provided[$package->getName()]->getConstraint()->matches($constraint)
			) {
				$request->install($package->getName(), $constraint);
			}
		}

		return $request;
	}
}
