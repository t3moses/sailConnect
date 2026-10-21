<?php

declare(strict_types=1);

namespace SailConnect\Auth;

final class View
{
    public function __construct(private string $dir)
    {
    }

    /** @param array<string,mixed> $vars */
    public function partial(string $template, array $vars = []): string
    {
        return (function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            require $__file;
            return (string) ob_get_clean();
        })($this->dir . '/' . $template . '.php', $vars);
    }

    /** @param array<string,mixed> $vars */
    public function page(string $name, array $vars = []): string
    {
        $content = $this->partial($name, $vars);
        return $this->partial('layout', $vars + ['content' => $content, 'title' => 'Sail Connect']);
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
