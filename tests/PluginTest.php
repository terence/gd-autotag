<?php

use WpPlugin\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function test_construct()
    {
        $plugin = new Plugin(__DIR__ . '/../gd-autotag.php');
        $this->assertInstanceOf(Plugin::class, $plugin);
    }
}
