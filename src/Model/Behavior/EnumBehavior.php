<?php
namespace Enum\Model\Behavior;

use ArrayObject;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\RulesChecker;
use Cake\Utility\Inflector;
use Enum\Model\Behavior\Strategy\AbstractStrategy;
use InvalidArgumentException;
use RuntimeException;

class EnumBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * - `defaultStrategy`: the default strategy to use.
     * - `implementedMethods`: custom table methods made accessible by this behavior.
     * - `lists`: the defined enumeration lists. Lists can use different strategies,
     *   use prefixes to differentiate them (defaults to the uppercased list name) and
     *   are persisted into a table's field (default to the underscored list name).
     *
     *   Example:
     *
     *   ```php
     *   $lists = [
     *       'priority' => [
     *           'strategy' => 'lookup',
     *           'prefix' => 'PRIORITY',
     *           'field' => 'priority',
     *           'errorMessage' => 'Invalid priority',
     *       ],
     *   ];
     *   ```
     *
     * @var array
     */
    protected $_defaultConfig = [
        'defaultStrategy' => 'lookup',
        'implementedMethods' => [
            'enum' => 'enum',
        ],
        'lists' => [],
    ];

    /**
     * Class map.
     *
     * @var array
     */
    protected $_classMap = [
        'lookup' => 'Enum\Model\Behavior\Strategy\LookupStrategy',
        'const' => 'Enum\Model\Behavior\Strategy\ConstStrategy',
        'enum' => 'Enum\Model\Behavior\Strategy\EnumStrategy',
    ];

    /**
     * Stack of strategies in use.
     *
     * @var array
     */
    protected $_strategies = [];

    /**
     * Initializes the behavior.
     *
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->_normalizeConfig();
    }

    /**
     * Getter/setter for strategies.
     *
     * @param string $alias
     * @param mixed $strategy Strategy name from the classmap,
     * @return \Enum\Model\Behavior\Strategy\AbstractStrategy
     */
    public function strategy($alias, $strategy)
    {
        if (!empty($this->_strategies[$alias])) {
            return $this->_strategies[$alias];
        }

        $this->_strategies[$alias] = $strategy;

        if ($strategy instanceof AbstractStrategy) {
            return $strategy;
        }

        if (isset($this->_classMap[$strategy])) {
            $class = $this->_classMap[$strategy];
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class not found for strategy (%s)', $strategy));
        }

        return $this->_strategies[$alias] = new $class($alias, $this->_table);
    }

    /**
     * Normalizes the strategies configuration and initializes the strategies.
     *
     * @return void
     */
    protected function _normalizeConfig()
    {
        $lists = $this->config('lists');
        $defaultStrategy = $this->config('defaultStrategy');

        foreach ($lists as $alias => $config) {
            if (is_numeric($alias)) {
                unset($lists[$alias]);
                $alias = $config;
                $config = [];
                $lists[$alias] = $config;
            }

            if (is_string($config)) {
                $config = ['prefix' => strtoupper($config)];
            }

            if (empty($config['strategy'])) {
                $config['strategy'] = $defaultStrategy;
            }

            $lists[$alias] =  $this->strategy($alias, $config['strategy'])
                ->initialize($config)
                ->config();
        }

        $this->config('lists', $lists, false);
    }

    /**
     * @param string $alias Defined list's alias/name.
     * @return array
     */
    public function enum($alias)
    {
        $config = $this->config('lists.' . $alias);
        if (empty($config)) {
            throw new RuntimeException();
        }

        return $this->strategy($alias, $config['strategy'])->enum($config);
    }

    /**
     * @param \Cake\Event\Event $event Event.
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(Event $event, RulesChecker $rules)
    {
        foreach ($this->config('lists') as $alias => $config) {
            $ruleName = 'isValid' . Inflector::classify($alias);
            $rules->add([$this, $ruleName], $ruleName, [
                'errorField' => $config['field'],
                'message' => $config['errorMessage']
            ]);
        }
        return $rules;
    }

    /**
     * Universal validation rule for lists.
     *
     * @param string $method
     * @param array $args
     */
    public function __call($method, $args)
    {
        if (strpos($method, 'isValid') !== 0) {
            throw new RuntimeException();
        }

        $alias = Inflector::underscore(str_replace('isValid', '', $method));

        if (!$config = $this->config('lists.' . $alias)) {
            throw new RuntimeException();
        }

        list($entity, $options) = $args;

        $value = $entity->{$config['field']};
        return array_key_exists($entity->{$config['field']}, $this->enum($alias));
    }
}
