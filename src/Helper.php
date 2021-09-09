<?php
declare (strict_types=1);

namespace think\admin;

use think\App;
use think\Container;
use think\Db;
use think\db\Query;

/**
 * 控制器挂件
 * Class Helper
 * @package think\admin
 */
abstract class Helper
{
    /**
     * 应用容器
     * @var App
     */
    public $app;

    /**
     * 数据库实例
     * @var Query
     */
    public $query;

    /**
     * 控制器实例
     * @var Controller
     */
    public $class;

    /**
     * Helper constructor.
     * @param App $app
     * @param Controller $class
     */
    public function __construct(Controller $class, App $app)
    {
        $this->app = $app;
        $this->class = $class;
    }

    /**
     * 获取数据库对象
     * @param string|Query $dbQuery
     * @return Db|Query
     */
    protected function buildQuery($dbQuery)
    {
        return is_string($dbQuery) ? $this->app->db->name($dbQuery) : $dbQuery;
    }

    /**
     * 实例对象反射
     * @param array $args
     * @return static
     */
    public static function instance(...$args): Helper
    {
        return Container::getInstance()->invokeClass(static::class, $args);
    }
}