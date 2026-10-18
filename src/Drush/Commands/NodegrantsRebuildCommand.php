<?php

declare(strict_types=1);

namespace Drupal\nodegrants\Drush\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\NodeAccessRebuild;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rebuilds the node_access table from the command line.
 *
 * Equivalent to the "Rebuild permissions" button at
 * /admin/reports/status/rebuild, or to running
 * `drush php:eval '\Drupal::service(\Drupal\node\NodeAccessRebuild::class)->rebuild();'`.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Rebuild the content access permissions (node_access table).',
  aliases: ['ng:rebuild'],
)]
final class NodegrantsRebuildCommand extends Command {

  use AutowireTrait;

  public const NAME = 'nodegrants:rebuild';

  /**
   * Constructs the command.
   *
   * The NodeAccessRebuild service only exists on Drupal 11.4+. On older
   * releases the container has no such service, AutowireTrait passes NULL and
   * execute() falls back to the deprecated node_access_rebuild() function.
   */
  public function __construct(
    protected readonly MessengerInterface $messenger,
    protected readonly ?NodeAccessRebuild $nodeAccessRebuild = NULL,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this->setHelp('Deletes every row in the node_access table and recalculates the grants for every node, newest first, in a single process. Run it after turning nodegrants on or off for a content type, or whenever Drupal reports that content access permissions need to be rebuilt.');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    if ($this->nodeAccessRebuild !== NULL) {
      $this->nodeAccessRebuild->rebuild();
    }
    elseif (function_exists('node_access_rebuild')) {
      node_access_rebuild();
    }
    else {
      $io->error('Unable to rebuild: neither the NodeAccessRebuild service nor node_access_rebuild() is available.');
      return self::FAILURE;
    }

    // Core reports the outcome through the messenger, and Drush relays every
    // pending message as a "[notice] Message: ..." line after the command.
    // Drop that status message so the result is reported once, below.
    $this->messenger->deleteByType(MessengerInterface::TYPE_STATUS);

    $io->success('Content access permissions have been rebuilt.');
    return self::SUCCESS;
  }

}
