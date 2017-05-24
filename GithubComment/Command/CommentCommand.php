<?php

namespace Lyrixx\GithubComment\Command;

// use Symfony\Component\Console\GuzzleConsolePlugin;
use Github\Client as Github;
use Github\Exception\RuntimeException;
use Github\HttpClient\HttpClient;
use Guzzle\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\ProcessBuilder;

class CommentCommand extends Command
{
    public function configure()
    {
        $this
            ->setName('comment')
            ->setDescription('Add a comment on all PR between a commit range.')
            ->setDefinition(array(
                new InputArgument('message', InputArgument::REQUIRED, 'message to post.'),
                new InputArgument('previous-revision', InputArgument::REQUIRED, 'Previously deployed revision.'),
                new InputArgument('new-revision', InputArgument::OPTIONAL, 'New deployed revision.', 'HEAD'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run.'),
                new InputOption('no-confirmation', null, InputOption::VALUE_NONE, 'Skip confirmation.'),
                new InputOption('organization', null, InputOption::VALUE_REQUIRED, 'organization name <comment>example: lyrixx</comment>.'),
                new InputOption('repository', null, InputOption::VALUE_REQUIRED, 'repository name <comment>example: GithubComment</comment>.'),
                new InputOption('work-tree', null, InputOption::VALUE_REQUIRED, 'git option'),
                new InputOption('git-dir', null, InputOption::VALUE_REQUIRED, 'git option.'),
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // First initialize GH client to set the token if no already done
        $ghClient = $this->getGithubClient($input, $output);

        $cmd = $this->createGitCommand($input, array(
            'log',
            '--format=%s',
            '--merges',
            sprintf('%s..%s', $input->getArgument('previous-revision'), $input->getArgument('new-revision'))
        ));

        $process = $this->runProcess($output, $cmd);

        $commits = explode("\n", trim($process->getOutput()));

        $prs = array_filter($commits, function ($v) {
            return preg_match('/#(\d+)/', $v, $matches);
        });

        if (!$prs) {
            $output->writeln('There is no merged PR in this commit range.');

            return 0;
        }

        $output->writeln('Merged prs:');
        foreach ($prs as $commit) {
            $output->writeln(sprintf(' * %s', $commit));
        }

        $message = $input->getArgument('message');
        $message = str_replace('\n', "\n", $message);

        $dialog = $this->getHelperSet()->get('question');
        if (!$input->getOption('no-confirmation') && !$dialog->ask($input, $output, new ConfirmationQuestion(sprintf('<info>Continue with message: "%s": [Y/n]</info> ', $message), true))) {
            $output->writeln('Aborting...');

            return 0;
        }

        $prs = array_map(function ($v) {
            preg_match('/#(\d+)/', $v, $matches);

            return $matches[1];
        }, $prs);

        $repositoryInfo = $this->getRepositoryInfo($input, $output);

        if ($output->isDebug()) {
            $output->writeln(sprintf('Repository info: %s', print_r($repositoryInfo, true)));
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<info>Dry-run, aborting<info>');

            return 0;
        }

        foreach ($prs as $pr) {
            try {
                $ghClient->api('issue')->comments()->create($repositoryInfo['organization'], $repositoryInfo['repository'], $pr, array('body' => $message));
            } catch (RuntimeException $e) {
                $output->writeln(sprintf('<error>Impossible to comment the PR %s/%s:#%d, message: "%s"<error>', $repositoryInfo['organization'], $repositoryInfo['repository'], $pr, $e->getMessage()));

                throw $e;
            }
        }

        $output->writeln('<info>All PR has been commented<info>');
    }

    private function getRepositoryInfo(InputInterface $input, OutputInterface $output)
    {
        $repository = $input->getOption('repository');
        if (!$repository) {
            $cmd = $this->createGitCommand($input, array(
                'remote',
                '--verbose',
            ));

            $process = $this->runProcess($output, $cmd);

            $remotes = explode("\n", trim($process->getOutput()));
            if (!$remotes) {
                throw new \RuntimeException(sprintf('Impossible to guess remote information. use --organization and --repository instead. remotes: %s', print_r($remotes, true)));
            }

            preg_match('{github\.com[:/](?P<organization>[a-z0-9\.-_]+)/(?P<repository>[a-z0-9\.-_]+)(\.git)?}i', $remotes[0], $matches);

            if (!$matches) {
                throw new \RuntimeException('Impossible to guess remote information. use --organization and --repository instead (regex does no work).');
            }

            $matches['repository'] = preg_replace('/(.git)$/', '', $matches['repository']);

            return $matches;
        }

        if (!preg_match('{^[a-z0-9\.]+$}i', $repository)) {
            throw new \InvalidArgumentException('The repository name is not valid');
        }

        $organization = $input->getOption('organization');

        if ($organization && !preg_match('{^[a-z0-9]+$}i', $organization)) {
            throw new \InvalidArgumentException('The organization name is not valid');
        }

        return array('organization' => $organization, 'repository' => $repository);
    }

    private function createGitCommand(InputInterface $input, array $rest = array())
    {
        $cmd = array('git');
        if ($input->getOption('work-tree')) {
            $cmd[] = '--work-tree='.$input->getOption('work-tree');
        }
        if ($input->getOption('git-dir')) {
            $cmd[] = '--git-dir='.$input->getOption('git-dir');
        }

        $cmd = array_merge($cmd, $rest);

        return $cmd;
    }

    private function runProcess(OutputInterface $output, array $cmd = array())
    {
        $process = ProcessBuilder::create($cmd)->getProcess();

        $this->getHelperSet()->get('process')->run($output, $process);

        if (!$process->isSuccessful()) {
            if (!$output->isDebug()) {
                $output->writeln('<comment>Rerun the command with "-vvv" option to get more information.</comment>');
            }

            $message = explode("\n", $process->getErrorOutput(), 2);

            throw new \RuntimeException(sprintf('Something went wrong with git: "%s"', $message[0]));
        }

        return $process;
    }

    private function getGithubClient(InputInterface $input, OutputInterface $output)
    {
        $credentials = $this->getHelperSet()->get('github')->getCredentials($input, $output);

        $guzzle = new Client('https://api.github.com/');
        // $guzzle->addSubscriber(new GuzzleConsolePlugin($this->output, $this->getHelperSet()->get('debug_formatter')));
        $github = new Github(new HttpClient(array(), $guzzle));
        $github->authenticate($credentials['token'], Github::AUTH_HTTP_TOKEN);

        return $github;
    }
}
