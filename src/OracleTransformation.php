<?php

declare(strict_types=1);

namespace Keboola\OracleTransformation;

use Keboola\OracleTransformation\Config\Block;
use Keboola\OracleTransformation\Config\Code;
use Keboola\OracleTransformation\Config\Script;
use Keboola\OracleTransformation\Exception\UserException;
use Psr\Log\LoggerInterface;
use SqlFormatter;
use Throwable;

class OracleTransformation
{
    private LoggerInterface $logger;

    private DatabaseAdapter $databaseAdapter;

    public function __construct(LoggerInterface $logger, DatabaseAdapter $databaseAdapter)
    {
        $this->logger = $logger;
        $this->databaseAdapter = $databaseAdapter->createConnection();
    }

    public function processBlocks(array $blocks): void
    {
        foreach ($blocks as $block) {
            $this->processBlock($block);
        }
    }

    private function processBlock(Block $block): void
    {
        $this->logger->info(sprintf('Processing block "%s".', $block->getName()));
        foreach ($block->getCodes() as $code) {
            $this->processCode($block, $code);
        }
    }

    private function processCode(Block $block, Code $code): void
    {
        $this->logger->info(sprintf('Processing code "%s".', $code->getName()));
        foreach ($code->getScripts() as $script) {
            $this->processScript($block, $script);
        }
    }

    private function processScript(Block $block, Script $script): void
    {
        $uncommentedQuery = SqlFormatter::removeComments($script->getSql(), false);

        $uncommentedQuery = trim($uncommentedQuery, ';');

        // Do not execute empty queries
        if (strlen(trim($uncommentedQuery)) === 0) {
            return;
        }

        if (strtoupper(substr($uncommentedQuery, 0, 6)) === 'SELECT') {
            return;
        }

        $this->logger->info(sprintf('Running query "%s".', $this->queryExcerpt($script->getSql())));

        try {
            $this->databaseAdapter->queryExecute($uncommentedQuery);
        } catch (Throwable $exception) {
            $message = sprintf(
                'Query "%s" in "%s" failed with error: "%s"',
                $this->queryExcerpt($script->getSql()),
                $block->getName(),
                $exception->getMessage()
            );
            throw new UserException($message, 0, $exception);
        }
    }

    private function queryExcerpt(string $query): string
    {
        if (mb_strlen($query) > 1000) {
            return mb_substr($query, 0, 500, 'UTF-8') . "\n...\n" . mb_substr($query, -500, null, 'UTF-8');
        }
        return $query;
    }
}
