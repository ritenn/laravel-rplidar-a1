<?php

namespace Ritenn\RplidarA1\Commands;


use Illuminate\Console\Command;
use Ritenn\RplidarA1\Facades\Memory;
use Ritenn\RplidarA1\Main\Process;

class ExecProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:exec';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start RPLidar background process';

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute command
     */
    public function handle()
    {
        Memory::remember('process_active', true);
        app()->make(Process::class);
    }


}