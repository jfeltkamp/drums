<?php

namespace Drush\Commands\drums;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAlias;
use DrupalFinder\DrupalFinder;
use Drush\Drush;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

/**
 * Edit this file to reflect your organization's needs.
 */
class MultiSiteCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;


  protected function getDrupalFinder() {
    if (!$this->drupalFinder) {
      $this->drupalFinder = new DrupalFinder();
    }
    return $this->drupalFinder;
  }

  /**
   * @command ms:sites
   *
   * @param string $cmd A parameter
   * @option bool $local The "local" option. Default: true
   *
   * @aliases ms
   *
   * Demonstrates a trivial command that takes a single required parameter.
   */
  public function msSites(InputInterface $input)
  {

    $this->io()->writeln('The parameter is ' . $input->getArgument('cmd') . ' and the "local" option is ' . $input->getOption('local'));

    $cmd = $input->getArgument('cmd');

    /** @var \Consolidation\SiteAlias\SiteAlias $alias */
    foreach ($this->getAliases($input->getOption('local')) as $alias) {
      $site_root = $this->getSiteRoot($alias);

      $process = $this->processManager()->shell('ls -la', $site_root);

      $result = $process->mustRun();
      $this->io()->write($result->getOutput());
      $this->io()->writeLn('#############################');

      /** @var \Consolidation\SiteProcess\SiteProcess $process */
      $process = $this->processManager()->drush($alias, $cmd, [], []);
      $process->mustRun();
      if ($process->isSuccessful()) {
       $this->logger()->success("Run $cmd on {$alias->name()}");
      }
      $this->io()->write($process->getOutput());
      /* */
    }
  }


  /**
   * @command ms:param
   *
   * @param string $param A parameter
   *
   * Demonstrates a trivial command that takes a single required parameter.
   */
  public function msParam($param)
  {
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $process = new Process(['drush', '-l', 'rulesfinder', 'status']);
    $process->run();

    // executes after the command finishes
    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }

    echo $process->getOutput();

    $this->io()->writeln('The parameter is ' . $drupalRoot);
  }

  /**
   * @command ms:drush
   *
   * @aliases msd
   *
   * @param string $alias_name A drush site alias name
   *
   * Demonstrate how to use the process manager to call a Drush
   * command. Use the alias manager to get a reference to @self
   * to use for this purpose.
   */
  public function msDrush($alias_name)
  {
    $alias = $this->siteAliasManager()->getAlias($alias_name);
    if ($alias->isLocal()) {

      $process = $this->processManager()->drush($alias, 'status', [], ['format' => 'json']);

      $result = $process->mustRun();
      $data = $process->getOutputAsJson();
      // echo serialize($data);
      $drush_script = basename($data['drush-script']);
      $this->io()->writeln("The Drush script is $drush_script");
    }
  }

  protected function getAliases($local=TRUE) {
    $collector = [];
    foreach ($this->siteAliasManager()->getMultiple() as $alias) {
      if (!$local || $alias->isLocal())
        $collector[] = $alias;
    }
    return $collector;
  }

  /**
   * Get site root folder.
   *
   * @param \Consolidation\SiteAlias\SiteAlias $alias
   *
   * @return string|null
   *
   * @Todo Find propper way to get site folder without firing drush status command.
   */
  public function getSiteRoot(SiteAlias $alias) {
    $site_root = $alias->get('site');
    if (!$site_root) {
      $process = $this->processManager()->drush($alias, 'status', [], ['format' => 'json']);
      $status_info = $process->mustRun()->getOutputAsJson();
      $site_root = $status_info['site'] ?? "site/{$alias->name()}";
      Drush::bootstrapManager()->drupalFinder();

    }
    return $site_root;
  }
}
