<?php

namespace Drupal\Console\Extension;

/**
 * Class ExtensionManager
 * @package Drupal\Console
 */
class Manager
{
    protected $drupalApi;
    protected $appRoot;

    /**
     * @var array
     */
    private $extensions = [];

    /**
     * @var array
     */
    private $filters = [];

    /**
     * @var string
     */
    private $extension = null;

    /**
     * ExtensionManager constructor.
     * @param $drupalApi
     * @param $appRoot
     */
    public function __construct($drupalApi, $appRoot)
    {
        $this->drupalApi = $drupalApi;
        $this->appRoot = $appRoot;
        $this->initialize();
    }

    /**
     * @return $this
     */
    public function showInstalled()
    {
        $this->filters['showInstalled'] = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function showUninstalled()
    {
        $this->filters['showUninstalled'] = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function showCore()
    {
        $this->filters['showCore'] = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function showNoCore()
    {
        $this->filters['showNoCore'] = true;
        return $this;
    }

    /**
     * @param string $nameOnly
     * @return array
     */
    public function getList($nameOnly)
    {
        return $this->getExtensions($this->extension, $nameOnly);
    }

    /**
     * @return $this
     */
    public function discoverModules()
    {
        $this->initialize();
        $this->discoverExtension('module');

        return $this;
    }

    /**
     * @return $this
     */
    public function discoverThemes()
    {
        $this->initialize();
        $this->discoverExtension('theme');

        return $this;
    }

    /**
     * @return $this
     */
    public function discoverProfiles()
    {
        $this->initialize();
        $this->discoverExtension('profile');

        return $this;
    }

    /**
     * @param string $extension
     */
    private function discoverExtension($extension)
    {
        $this->extension = $extension;
        $this->extensions[$extension] = $this->discoverExtensions($extension);
    }

    /**
     * initializeFilters
     */
    private function initialize()
    {
        $this->extension = 'module';
        $this->extensions = [
            'module' =>[],
            'theme' =>[],
            'profile' =>[],
        ];
        $this->filters = [
            'showInstalled' => false,
            'showUninstalled' => false,
            'showCore' => false,
            'showNoCore' => false
        ];
    }

    /**
     * @param string     $type
     * @param bool|false $nameOnly
     * @return array
     */
    private function getExtensions(
        $type = 'module',
        $nameOnly = false
    ) {
        $showInstalled = $this->filters['showInstalled'];
        $showUninstalled = $this->filters['showUninstalled'];
        $showCore = $this->filters['showCore'];
        $showNoCore = $this->filters['showNoCore'];

        $extensions = [];
        if (!$this->extensions[$type]) {
            return [];
        }

        foreach ($this->extensions[$type] as $extension) {
            $name = $extension->getName();

            $isInstalled = false;
            if (property_exists($extension, 'status')) {
                $isInstalled = ($extension->status)?true:false;
            }
            if (!$showInstalled && $isInstalled) {
                continue;
            }
            if (!$showUninstalled && !$isInstalled) {
                continue;
            }
            if (!$showCore && $extension->origin == 'core') {
                continue;
            }
            if (!$showNoCore && $extension->origin != 'core') {
                continue;
            }
            if ($nameOnly) {
                $extensions[] = $name;
            } else {
                $extensions[$name] = $extension;
            }
        }

        return $extensions;
    }

    /**
     * @param string $type
     * @return \Drupal\Core\Extension\Extension[]
     */
    private function discoverExtensions($type)
    {
        if ($type === 'module') {
            $this->drupalApi->loadLegacyFile('/core/modules/system/system.module');
            system_rebuild_module_data();
        }

        /*
         * @see Remove DrupalExtensionDiscovery subclass once
         * https://www.drupal.org/node/2503927 is fixed.
         */
        $discovery = new Discovery($this->appRoot);
        $discovery->reset();

        return $discovery->scan($type);
    }

    /**
     * @param string $name
     * @return \Drupal\Console\Extension\Extension
     */
    public function getModule($name)
    {
        if ($extension = $this->getExtension('module', $name)) {
            return $this->createExtension($extension);
        }

        return null;
    }

    /**
     * @param string $type
     * @param string $name
     *
     * @return \Drupal\Core\Extension\Extension
     */
    private function getExtension($type, $name)
    {
        if (!$this->extensions[$type]) {
            $this->discoverExtension($type);
        }

        if (array_key_exists($name, $this->extensions[$type])) {
            return $this->extensions[$type][$name];
        }

        return null;
    }

    /**
     * @param \Drupal\Core\Extension\Extension $extension
     * @return \Drupal\Console\Extension\Extension
     */
    private function createExtension($extension)
    {
        $consoleExtension = new Extension(
            $this->appRoot,
            $extension->getType(),
            $extension->getPathname(),
            $extension->getExtensionFilename()
        );
        $consoleExtension->unserialize($extension->serialize());

        return $consoleExtension;
    }
}
