<?php
declare (strict_types=1);
namespace think\admin;

use think\admin\service\ProcessService;
use think\admin\service\QueueService;
use think\App;

/**
 * 任务基础类
 * Class Queue
 * @package think\admin
 */
abstract class Queue
{
    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 任务控制服务
     * @var QueueService
     */
    protected $queue;

    /**
     * 进程控制服务
     * @var ProcessService
     */
    protected $process;

    /**
     * Queue constructor.
     * @param App $app
     * @param ProcessService $process
     */
    public function __construct(App $app, ProcessService $process)
    {
        $this->app = $app;
        $this->process = $process;
    }

    /**
     * 初始化任务数据
     * @param QueueService $queue
     * @return $this
     */
    public function initialize(QueueService $queue): Queue
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * 执行任务处理内容
     * @param array $data
     */
    abstract public function execute($data = []);

    /**
     * 设置任务的进度
     * @param null|string $message 进度消息
     * @param null|integer $progress 进度数值
     * @return Queue
     */
    protected function setQueueProgress(?string $message = null, $progress = null): Queue
    {
        $this->queue->progress(2, $message, $progress);
        return $this;
    }

    /**
     * 设置成功的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueSuccess(string $message): void
    {
        $this->queue->success($message);
    }

    /**
     * 设置失败的消息
     * @param string $message 消息内容
     * @throws Exception
     */
    protected function setQueueError(string $message): void
    {
        $this->queue->error($message);
    }
}