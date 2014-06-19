<?php

namespace Lyrixx\GithubComment\Helper;

use Github\Client as Github;
use Github\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class GithubHelper extends Helper
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
    public function getName()
    {
        return 'github';
    }

    public function getCredentials(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($file = getenv('HOME').'/.'.$this->name)) {
            return json_decode(file_get_contents($file), true);
        }

        $output->writeln('Before using this command, you must create a Github token.');
        $output->writeln('This is the only time you will need to provide your Github username/password.');
        $output->writeln('Your password is never stored.');

        while (!$result = $this->createToken($input, $output)) {
            $output->writeln('<error>Bad credentials, try again.</error>');
        }

        file_put_contents(getenv('HOME').'/.'.$this->name, json_encode($result));

        return $result;
    }

    private function createToken(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('question');
        $username = $dialog->ask($input, $output, new Question('<info>Your Github username:</info> '));
        $question = new Question('<info>Your Github password:</info> ');
        $question->setHidden(true);
        $password = $dialog->ask($input, $output, $question);

        $github = new Github();
        $github->authenticate($username, $password, Github::AUTH_HTTP_PASSWORD);
        try {
            $token = $github->api('authorizations')->create(array('scopes' => array('repo'), 'note' => $this->name));

            return ['username' => $username, 'token' => $token['token']];
        } catch (RuntimeException $e) {
            return false;
        }
    }
}
