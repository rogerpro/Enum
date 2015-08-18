<?php
namespace Enum\Model\Behavior\Strategy;

use Cake\Core\InstanceConfigTrait;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use RuntimeException;

abstract class AbstractStrategy implements StrategyInterface
{

    use InstanceConfigTrait;

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    /**
     * List of defined group prefixes.
     *
     * @var array
     */
    protected $_prefixes = [];

    /**
     * Target table.
     *
     * @var \Cake\ORM\Table
     */
    protected $_table;

    /**
     * @param array $config Configuration.
     * @param \Cake\ORM\Table $table Target table.
     */
    public function __construct($alias, Table $table)
    {
        $this->_alias = $alias;
        $this->_table = $table;
    }

    /**
     * @inheritdoc
     */
    public function initialize($config)
    {
        if (is_string($config)) {
            $config = ['prefix' => $config];
        }

        if (empty($config['prefix'])) {
            $config['prefix'] = $this->_generatePrefix();
        }

        if (empty($config['field'])) {
            $config['field'] = Inflector::underscore(Inflector::singularize($this->_alias));
        }

        if (empty($config['errorMessage'])) {
            $config['errorMessage'] = __d('cake', 'The provided value is invalid');
        }

        $this->config($config);

        return $this;
    }

    /**
     * Generates default prefix for strategy.
     *
     * @return string
     */
    protected function _generatePrefix()
    {
        $prefix = Inflector::underscore(Inflector::singularize($this->_table->alias()));
        $prefix .= '_' . $this->_alias;
        if (!$this->hasPrefix($prefix)) {
            if (!$this->hasPrefix($this->_alias)) {
                throw new RuntimeException(sprintf('Undefined prefix for strategy (%s)', $this->_alias));
            }
            $prefix = $this->_alias;
        }

        return strtoupper($prefix);
    }
}
