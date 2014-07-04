<?php

namespace Lyrixx\GithubComment;

use Lyrixx\GithubComment\Command as Commands;
use Lyrixx\GithubComment\Helper\GithubHelper;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('GithubComment', '0.0.3');

        $this->add(new Commands\CommentCommand());
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    protected function getCommandName(InputInterface $input)
    {
        return 'comment';
    }

    protected function getDefaultHelperSet()
    {
        $defaultHelpers = parent::getDefaultHelperSet();

        $defaultHelpers->set(new GithubHelper($this->getName()));

        return $defaultHelpers;
    }
}
