#!/usr/bin/env php
<?php

declare(strict_types=1);

const VERSION_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/';

try {
    exit(main($argv));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);

    exit(1);
}

function main(array $argv): int
{
    $options = parseArguments($argv);

    if ($options['help']) {
        writeUsage(STDOUT);

        return 0;
    }

    $projectRoot = dirname(__DIR__);
    $composerPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';

    if (! is_file($composerPath)) {
        throw new RuntimeException('composer.json nao encontrado em ' . $projectRoot);
    }

    if (! chdir($projectRoot)) {
        throw new RuntimeException('Nao foi possivel acessar o diretorio do projeto.');
    }

    if (! $options['commit'] && $options['tag']) {
        throw new RuntimeException('Nao e possivel criar a tag sem criar o commit de release. Use --no-tag junto com --no-commit.');
    }

    $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
    $currentVersion = $composer['version'] ?? null;

    if (! is_string($currentVersion) || ! preg_match(VERSION_PATTERN, $currentVersion)) {
        throw new RuntimeException('O campo "version" do composer.json precisa estar em formato semver.');
    }

    $nextVersion = resolveNextVersion($currentVersion, $options['target'], $options['preid']);

    if (compareSemver($nextVersion, $currentVersion) <= 0) {
        throw new RuntimeException(sprintf('A proxima versao (%s) precisa ser maior que a atual (%s).', $nextVersion, $currentVersion));
    }

    $tagPrefix = $options['tagPrefix'] ?? detectTagPrefix($currentVersion);
    $tagName = $tagPrefix . $nextVersion;

    if ($options['tag'] && gitTagExists($tagName)) {
        throw new RuntimeException(sprintf('A tag "%s" ja existe.', $tagName));
    }

    if (! $options['dryRun']) {
        $isClean = isGitWorkingTreeClean();

        if (($options['commit'] || $options['tag']) && ! $isClean) {
            throw new RuntimeException('A arvore git precisa estar limpa antes do release. Commit ou stash suas alteracoes primeiro.');
        }

        if (! $options['commit'] && ! $options['tag'] && ! $isClean && ! $options['allowDirty']) {
            throw new RuntimeException('Existem alteracoes locais. Use --allow-dirty se quiser somente atualizar o composer.json.');
        }
    }

    $composer['version'] = $nextVersion;
    $encodedComposer = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

    if ($options['dryRun']) {
        fwrite(STDOUT, sprintf("Versao atual: %s\n", $currentVersion));
        fwrite(STDOUT, sprintf("Proxima versao: %s\n", $nextVersion));

        if ($options['commit']) {
            fwrite(STDOUT, sprintf("Commit de release: %s\n", $options['message'] ?? defaultCommitMessage($nextVersion)));
        }

        if ($options['tag']) {
            fwrite(STDOUT, sprintf("Tag de release: %s\n", $tagName));
        }

        return 0;
    }

    file_put_contents($composerPath, $encodedComposer);
    fwrite(STDOUT, sprintf("composer.json atualizado: %s -> %s\n", $currentVersion, $nextVersion));

    if ($options['commit']) {
        runCommand(['git', 'add', 'composer.json']);
        $commitMessage = $options['message'] ?? defaultCommitMessage($nextVersion);
        runCommand(['git', 'commit', '-m', $commitMessage]);
        fwrite(STDOUT, sprintf("Commit criado: %s\n", $commitMessage));
    }

    if ($options['tag']) {
        $tagMessage = $options['tagMessage'] ?? defaultTagMessage($nextVersion);
        runCommand(['git', 'tag', '-a', $tagName, '-m', $tagMessage]);
        fwrite(STDOUT, sprintf("Tag criada: %s\n", $tagName));
    }

    if ($options['commit'] || $options['tag']) {
        fwrite(STDOUT, "Proximo passo: git push origin main --follow-tags" . PHP_EOL);
    }

    return 0;
}

/**
 * @return array{
 *     target: string,
 *     help: bool,
 *     dryRun: bool,
 *     allowDirty: bool,
 *     commit: bool,
 *     tag: bool,
 *     preid: string,
 *     tagPrefix: ?string,
 *     message: ?string,
 *     tagMessage: ?string
 * }
 */
function parseArguments(array $argv): array
{
    $options = [
        'target' => null,
        'help' => false,
        'dryRun' => false,
        'allowDirty' => false,
        'commit' => true,
        'tag' => true,
        'preid' => 'beta',
        'tagPrefix' => null,
        'message' => null,
        'tagMessage' => null,
    ];

    for ($index = 1, $count = count($argv); $index < $count; $index++) {
        $argument = $argv[$index];

        if ($argument === '-h' || $argument === '--help') {
            $options['help'] = true;
            continue;
        }

        if ($argument === '--dry-run') {
            $options['dryRun'] = true;
            continue;
        }

        if ($argument === '--allow-dirty') {
            $options['allowDirty'] = true;
            continue;
        }

        if ($argument === '--no-commit') {
            $options['commit'] = false;
            continue;
        }

        if ($argument === '--no-tag') {
            $options['tag'] = false;
            continue;
        }

        if (str_starts_with($argument, '--preid=')) {
            $options['preid'] = substr($argument, strlen('--preid='));
            continue;
        }

        if ($argument === '--preid') {
            $options['preid'] = nextOptionValue($argv, ++$index, '--preid');
            continue;
        }

        if (str_starts_with($argument, '--tag-prefix=')) {
            $options['tagPrefix'] = substr($argument, strlen('--tag-prefix='));
            continue;
        }

        if ($argument === '--tag-prefix') {
            $options['tagPrefix'] = nextOptionValue($argv, ++$index, '--tag-prefix');
            continue;
        }

        if (str_starts_with($argument, '--message=')) {
            $options['message'] = substr($argument, strlen('--message='));
            continue;
        }

        if ($argument === '--message') {
            $options['message'] = nextOptionValue($argv, ++$index, '--message');
            continue;
        }

        if (str_starts_with($argument, '--tag-message=')) {
            $options['tagMessage'] = substr($argument, strlen('--tag-message='));
            continue;
        }

        if ($argument === '--tag-message') {
            $options['tagMessage'] = nextOptionValue($argv, ++$index, '--tag-message');
            continue;
        }

        if (str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Opcao desconhecida: ' . $argument);
        }

        if ($options['target'] !== null) {
            throw new InvalidArgumentException('Informe apenas um tipo de release ou uma versao explicita.');
        }

        $options['target'] = $argument;
    }

    if (! $options['help'] && $options['target'] === null) {
        throw new InvalidArgumentException('Informe o tipo de release (major, minor, patch, prerelease...) ou a versao desejada.');
    }

    if (! preg_match('/^[0-9A-Za-z-]+$/', $options['preid'])) {
        throw new InvalidArgumentException('O valor de --preid deve conter apenas letras, numeros e hifen.');
    }

    return $options;
}

function nextOptionValue(array $argv, int $index, string $option): string
{
    if (! array_key_exists($index, $argv) || str_starts_with($argv[$index], '--')) {
        throw new InvalidArgumentException(sprintf('A opcao %s precisa de um valor.', $option));
    }

    return $argv[$index];
}

function writeUsage($stream): void
{
    fwrite($stream, <<<TXT
Uso:
  composer release -- patch
  composer release -- minor --dry-run
  composer release -- 0.2.0
  php scripts/release.php prerelease --preid=rc

Tipos suportados:
  major, minor, patch, premajor, preminor, prepatch, prerelease
  ou uma versao explicita em semver, por exemplo: 1.4.0 ou 1.4.0-rc.1

Opcoes:
  --dry-run       Mostra a proxima versao sem gravar nada
  --no-commit     Atualiza apenas o composer.json
  --no-tag        Nao cria tag git
  --allow-dirty   Permite atualizar apenas o composer.json com a arvore suja
  --preid=beta    Prefixo para pre-release (beta, rc, alpha...)
  --tag-prefix=v  Forca prefixo da tag
  --message=...   Mensagem do commit de release
  --tag-message=... Mensagem da tag anotada

Fluxo recomendado:
  1. Commitar as alteracoes do pacote
  2. Rodar composer release -- patch|minor|major
  3. Rodar git push origin main --follow-tags

TXT
    );
}

function resolveNextVersion(string $currentVersion, string $target, string $preid): string
{
    $current = parseSemver($currentVersion);

    switch ($target) {
        case 'major':
            return formatSemver($current['major'] + 1, 0, 0);
        case 'minor':
            return formatSemver($current['major'], $current['minor'] + 1, 0);
        case 'patch':
            return formatSemver($current['major'], $current['minor'], $current['patch'] + 1);
        case 'premajor':
            return formatSemver($current['major'] + 1, 0, 0, $preid . '.1');
        case 'preminor':
            return formatSemver($current['major'], $current['minor'] + 1, 0, $preid . '.1');
        case 'prepatch':
            return formatSemver($current['major'], $current['minor'], $current['patch'] + 1, $preid . '.1');
        case 'prerelease':
            if ($current['prerelease'] !== null) {
                return formatSemver($current['major'], $current['minor'], $current['patch'], incrementPrerelease($current['prerelease'], $preid));
            }

            return formatSemver($current['major'], $current['minor'], $current['patch'] + 1, $preid . '.1');
        default:
            if (! preg_match(VERSION_PATTERN, $target)) {
                throw new InvalidArgumentException('Tipo de release invalido: ' . $target);
            }

            return $target;
    }
}

/**
 * @return array{major:int,minor:int,patch:int,prerelease:?string,build:?string}
 */
function parseSemver(string $version): array
{
    if (! preg_match(VERSION_PATTERN, $version, $matches)) {
        throw new InvalidArgumentException('Versao semver invalida: ' . $version);
    }

    return [
        'major' => (int) $matches[1],
        'minor' => (int) $matches[2],
        'patch' => (int) $matches[3],
        'prerelease' => ($matches[4] ?? '') !== '' ? $matches[4] : null,
        'build' => ($matches[5] ?? '') !== '' ? $matches[5] : null,
    ];
}

function formatSemver(int $major, int $minor, int $patch, ?string $prerelease = null, ?string $build = null): string
{
    $version = sprintf('%d.%d.%d', $major, $minor, $patch);

    if ($prerelease !== null && $prerelease !== '') {
        $version .= '-' . $prerelease;
    }

    if ($build !== null && $build !== '') {
        $version .= '+' . $build;
    }

    return $version;
}

function incrementPrerelease(string $prerelease, string $preid): string
{
    $parts = explode('.', $prerelease);

    if ($parts[0] !== $preid) {
        return $preid . '.1';
    }

    $lastIndex = array_key_last($parts);
    $lastPart = $parts[$lastIndex];

    if (ctype_digit($lastPart)) {
        $parts[$lastIndex] = (string) ((int) $lastPart + 1);

        return implode('.', $parts);
    }

    $parts[] = '1';

    return implode('.', $parts);
}

function compareSemver(string $leftVersion, string $rightVersion): int
{
    $left = parseSemver($leftVersion);
    $right = parseSemver($rightVersion);

    foreach (['major', 'minor', 'patch'] as $key) {
        if ($left[$key] !== $right[$key]) {
            return $left[$key] <=> $right[$key];
        }
    }

    return comparePrerelease($left['prerelease'], $right['prerelease']);
}

function comparePrerelease(?string $left, ?string $right): int
{
    if ($left === $right) {
        return 0;
    }

    if ($left === null) {
        return 1;
    }

    if ($right === null) {
        return -1;
    }

    $leftParts = explode('.', $left);
    $rightParts = explode('.', $right);
    $maxParts = max(count($leftParts), count($rightParts));

    for ($index = 0; $index < $maxParts; $index++) {
        $leftPart = $leftParts[$index] ?? null;
        $rightPart = $rightParts[$index] ?? null;

        if ($leftPart === $rightPart) {
            continue;
        }

        if ($leftPart === null) {
            return -1;
        }

        if ($rightPart === null) {
            return 1;
        }

        $leftNumeric = ctype_digit($leftPart);
        $rightNumeric = ctype_digit($rightPart);

        if ($leftNumeric && $rightNumeric) {
            return ((int) $leftPart) <=> ((int) $rightPart);
        }

        if ($leftNumeric) {
            return -1;
        }

        if ($rightNumeric) {
            return 1;
        }

        return $leftPart <=> $rightPart;
    }

    return 0;
}

function detectTagPrefix(string $currentVersion): string
{
    if (gitTagExists('v' . $currentVersion)) {
        return 'v';
    }

    if (gitTagExists($currentVersion)) {
        return '';
    }

    $latestTag = trim(runCommand(['git', 'tag', '--sort=-version:refname'], false)['output']);

    if ($latestTag === '') {
        return '';
    }

    $firstTag = strtok($latestTag, PHP_EOL);

    return str_starts_with((string) $firstTag, 'v') ? 'v' : '';
}

function gitTagExists(string $tagName): bool
{
    $result = runCommand(['git', 'tag', '--list', $tagName], false, false);

    return $result['exitCode'] === 0 && trim($result['output']) === $tagName;
}

function isGitWorkingTreeClean(): bool
{
    $result = runCommand(['git', 'status', '--porcelain'], false, false);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException('Nao foi possivel verificar o estado do git.');
    }

    return trim($result['output']) === '';
}

/**
 * @param array<int, string> $command
 * @return array{exitCode:int,output:string}
 */
function runCommand(array $command, bool $captureStderr = true, bool $throwOnFailure = true): array
{
    $shellCommand = implode(' ', array_map('escapeshellarg', $command));

    if ($captureStderr) {
        $shellCommand .= ' 2>&1';
    }

    $output = [];
    $exitCode = 0;
    exec($shellCommand, $output, $exitCode);

    $result = [
        'exitCode' => $exitCode,
        'output' => implode(PHP_EOL, $output),
    ];

    if ($throwOnFailure && $exitCode !== 0) {
        $message = trim($result['output']);
        throw new RuntimeException($message !== '' ? $message : 'Falha ao executar: ' . $shellCommand);
    }

    return $result;
}

function defaultCommitMessage(string $version): string
{
    return sprintf('chore(release): %s', $version);
}

function defaultTagMessage(string $version): string
{
    return sprintf('Release %s', $version);
}
