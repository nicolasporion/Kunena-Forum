<?php
/**
 * Kunena Component
 * @package Kunena.Administrator.Template
 * @subpackage Categories
 *
 * @copyright (C) 2008 - 2013 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

/**
 * Implements Kunena layouts for the views.
 *
 * This class is part of Kunena HMVC implementation, allowing calls to
 * any layout file.
 *
 * <code>
 *	echo KunenaLayout::factory('pagination')->set('pagination', $this->pagination);
 *	echo KunenaLayout::factory('pagination/footer')->set('pagination', $this->pagination);
 * </code>
 *
 * Individual layout classes are located in /components/com_kunena/layouts,
 * but they are not needed to get layouts to work. They are useful, though,
 * if template files would otherwise have too much code in them.
 *
 * Layout template files can be found from /components/com_Kunena/template/[template]/layouts.
 * Default layout can be overridden by ->setLayout():
 *
 * <code>
 *	echo KunenaLayout::factory('pagination')->set('pagination', $this->pagination)->setLayout('mini');
 * </code>
 *
 * @see KunenaRequest
 */
class KunenaLayout
{
	/**
	 * Layout name.
	 * @var string
	 */
	protected $name = '';

	/**
	 * The view layout.
	 *
	 * @var    string
	 */
	protected $layout = 'default';

	/**
	 * The paths queue.
	 *
	 * @var    SplPriorityQueue
	 */
	protected $paths;

	/**
	 * Support for closure variables.
	 *
	 * @var array
	 */
	protected $closures = array();

	/**
	 * Method to instantiate the layout.
	 *
	 * @param	string			$name
	 * @param   SplPriorityQueue  $paths  The paths queue.
	 */
	public function __construct($name, SplPriorityQueue $paths = null)
	{
		// Setup dependencies.
		$this->name = $name;
		$this->paths = isset($paths) ? $paths : $this->loadPaths();
	}

	/**
	 * Magic toString method that is a proxy for the render method.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		try {
			return (string) $this->render();
		} catch (Exception $e) {
			// Exceptions aren't allowed in string conversion, use PHP error instead.
			trigger_error($e->getMessage(), E_USER_ERROR);
			return '';
		}
	}

	/**
	 * Method to escape output.
	 *
	 * @param   string  $output  The output to escape.
	 *
	 * @return  string  The escaped output.
	 *
	 * @see     JView::escape()
	 */
	public function escape($output)
	{
		// Escape the output.
		return htmlspecialchars($output, ENT_COMPAT, 'UTF-8');
	}

	/**
	 * Method to render the view.
	 *
	 * @return  string  The rendered view.
	 * @throws  Exception|RunTimeException
	 */
	public function render()
	{
		KUNENA_PROFILER ? KunenaProfiler::instance()->start("render layout '{$this->name}'") : null;
		// Get the layout path.
		$path = $this->getPath($this->getLayout());

		// Check if the layout path was found.
		if (!$path) {
			KUNENA_PROFILER ? KunenaProfiler::instance()->stop("render layout '{$this->name}'") : null;
			throw new RuntimeException("Layout Path For '{$this->name}:{$this->layout}' Not Found");
		}

		// Start an output buffer.
		ob_start();

		// Load the layout.
		try {
			include $path;
		} catch (Exception $e) {
			KUNENA_PROFILER ? KunenaProfiler::instance()->stop("render layout '{$this->name}'") : null;
			throw $e;
		}

		// Get the layout contents.
		$output = ob_get_clean();
		if (JDEBUG || KunenaConfig::getInstance()->get('debug')) {
			$output = trim($output);
			$output = "\n<!-- START {$path} -->\n{$output}\n<!-- END {$path} -->\n";
		}

		KUNENA_PROFILER ? KunenaProfiler::instance()->stop("render layout '{$this->name}'") : null;
		return $output;
	}

	/**
	 * Method to get the view layout.
	 *
	 * @return  string  The layout name.
	 */
	public function getLayout()
	{
		return $this->layout;
	}

	/**
	 * Method to get the layout path.
	 *
	 * @param   string  $layout  The layout name, defaulting to the current one.
	 *
	 * @return  mixed  The layout file name if found, false otherwise.
	 */
	public function getPath($layout = null)
	{
		if (!$layout) {
			$layout = $this->getLayout();
		}
		// Get the layout file name.
		$file = JPath::clean($layout . '.php');

		$paths = array();
		foreach (clone $this->paths as $path) {
			$paths[] = $path;
		}
		// Find the layout file path.
		$path = JPath::find($paths, $file);

		return $path;
	}

	/**
	 * Method to get the view paths.
	 *
	 * @return  SplPriorityQueue  The paths queue.
	 */
	public function getPaths()
	{
		return $this->paths;
	}

	/**
	 * Method to set the view layout.
	 *
	 * @param   string  $layout  The layout name.
	 *
	 * @return  KunenaLayout  Method supports chaining.
	 */
	public function setLayout($layout)
	{
		$this->layout = $layout;

		return $this;
	}

	/**
	 * Method to set the view paths.
	 *
	 * @param   SplPriorityQueue  $paths  The paths queue.
	 *
	 * @return  KunenaLayout  Method supports chaining.
	 */
	public function setPaths(SplPriorityQueue $paths)
	{
		$this->paths = $paths;

		return $this;
	}

	/**
	 * Modifies a property of the object, creating it if it does not already exist.
	 *
	 * @param   string  $property  The name of the property.
	 * @param   mixed   $value     The value of the property to set.
	 *
	 * @return  KunenaLayout  Method supports chaining.
	 */
	public function set($property, $value = null)
	{
		$isFactory = is_object($value) && method_exists($value, '__invoke');
		if ($isFactory) {
			$this->closures[$property] = $value;
		} else {
			$this->$property = $value;
		}

		return $this;
	}

	/**
	 * Property overloading.
	 *
	 * @param $property
	 * @param $value
	 */
	public function __set($property, $value)
	{
		$this->set($property, $value);
	}

	/**
	 * Property overloading.
	 *
	 * @param $property
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __get($property)
	{
		 if (!array_key_exists($property, $this->closures)) {
            throw new InvalidArgumentException(sprintf('Property "%s" is not defined.', $property));
        }

        return $this->closures[$property]();
	}

	/**
	 * Property overloading.
	 *
	 * @param $property
	 * @return bool
	 */
	public function __isset($property)
	{
		return array_key_exists($property, $this->closures);
	}

	/**
	 * Set the object properties based on a named array/hash.
	 *
	 * @param   mixed  $properties  Either an associative array or another object.
	 *
	 * @return  KunenaLayout  Method supports chaining.
	 *
	 * @see     set()
	 * @throws \InvalidArgumentException
	 */
	public function setProperties($properties)
	{
		if (!is_array($properties) && !is_object($properties)) {
			throw new \InvalidArgumentException('Parameter should be either array or an object.');
		}

		foreach ((array) $properties as $k => $v) {
			// Use the set function which might be overridden.
			$this->set($k, $v);
		}

		return $this;
	}

	/**
	 * Method to load the paths queue.
	 *
	 * @return  SplPriorityQueue  The paths queue.
	 */
	protected function loadPaths()
	{
		return new SplPriorityQueue();
	}

	/**
	 * Returns layout class.
	 *
	 * <code>
	 *	// Output pagination/pages layout with current cart instance.
	 *	echo KunenaLayout::factory('pagination/pages')->set('pagination', $this->pagination);
	 * </code>
	 *
	 * @param   mixed $paths String or array of strings.
	 * @return  KunenaLayout
	 */
	public static function factory($paths) {
		$paths = (array) $paths;

		$app = JFactory::getApplication();
		// Add all paths for the template overrides.
		$templatePaths = new SplPriorityQueue();
		if ($app->isAdmin()) {
			$template = KunenaFactory::getAdminTemplate();
			$base = 'layouts';
		} else {
			$template = KunenaFactory::getTemplate();
			$base = 'html/layouts';
		}

		foreach ($paths as $path) {
			if (!$path) continue;

			$path = (string) preg_replace('|\\\|', '/', strtolower($path));
			$lookup = $template->getTemplatePaths("{$base}/{$path}", true);
			foreach ($lookup as $loc) {
				$templatePaths->insert($loc, 1);
			}
		}

		// Go through all the matching layouts.
		foreach ($paths as $path) {
			if (!$path) continue;

			// Attempt to load layout class if it doesn't exist.
			$class = 'KunenaLayout' . (string) preg_replace('/[^A-Z0-9_]/i', '', $path);
			if (!class_exists($class)) {
				$fpath = (string) preg_replace('|\\\|', '/', strtolower($path));
				$filename = JPATH_BASE . "/components/com_kunena/layout/{$fpath}.php";
				if (!is_file($filename)) {
					continue;
				}
				require_once $filename;
			}

			// Create layout object.
			return new $class($path, $templatePaths);
		}

		// Create default layout object.
		return new KunenaLayout($path, $templatePaths);
	}
}