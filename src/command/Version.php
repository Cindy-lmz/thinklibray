<?php



declare (strict_types=1);

namespace think\admin\command;

use think\admin\Command;
use think\console\Input;
use think\console\Output;

/**
 * 框架版本号指令
 * Class Version
 * @package think\admin\command
 */
class Version extends Command
{
    protected function configure()
    {
        $this->setName('xadmin:version');
        $this->setDescription("ThinkLibrary and ThinkPHP Version for ThinkAdmin");
    }

    /**
     * @param Input $input
     * @param Output $output
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('ThinkPHP ' . $this->app->version());
        $output->writeln('ThinkLIB ' . $this->process->version());
    }
}