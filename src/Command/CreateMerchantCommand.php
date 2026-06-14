<?php

namespace App\Command;

use App\Entity\Merchant;
use App\Enum\Status;
use App\Repository\MerchantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:merchant:create',
    description: 'Создаёт мерчанта и генерирует пару API-ключей (apiKey + apiSecret).',
)]
final class CreateMerchantCommand extends Command
{
    private const NAME_MAX_LENGTH = 128;
    private const CURRENCY_MAX_LENGTH = 16;
    private const API_KEY_PREFIX = 'mk_';
    private const API_KEY_BYTES = 16;
    private const API_SECRET_BYTES = 32;

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Название мерчанта (до '.self::NAME_MAX_LENGTH.' символов).',
            )
            ->addArgument(
                'baseCurrency',
                InputArgument::OPTIONAL,
                'Базовая валюта/тикер (до '.self::CURRENCY_MAX_LENGTH.' символов, например USD, EUR, USDT).',
            )
        ;
    }

    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->promptOrArgument(
            $io,
            $input,
            'Название мерчанта',
            $input->getArgument('name'),
        );

        if ($name === null) {
            $io->error('Название мерчанта обязательно.');

            return Command::INVALID;
        }

        $name = trim($name);
        if ($name === '') {
            $io->error('Название мерчанта не может быть пустым.');

            return Command::INVALID;
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $io->error(sprintf(
                'Название длиннее %d символов (получено %d).',
                self::NAME_MAX_LENGTH,
                mb_strlen($name),
            ));

            return Command::INVALID;
        }

        $baseCurrency = $this->promptOrArgument(
            $io,
            $input,
            'Базовая валюта (тикер, до '.self::CURRENCY_MAX_LENGTH.' символов)',
            $input->getArgument('baseCurrency'),
            'USD',
        );

        if ($baseCurrency === null) {
            $io->error('Базовая валюта обязательна.');

            return Command::INVALID;
        }

        $baseCurrency = strtoupper(trim($baseCurrency));
        if ($baseCurrency === '') {
            $io->error('Базовая валюта не может быть пустой.');

            return Command::INVALID;
        }

        if (mb_strlen($baseCurrency) > self::CURRENCY_MAX_LENGTH) {
            $io->error(sprintf(
                'Код валюты длиннее %d символов (получено %d).',
                self::CURRENCY_MAX_LENGTH,
                mb_strlen($baseCurrency),
            ));

            return Command::INVALID;
        }

        $apiKey = self::API_KEY_PREFIX . bin2hex(random_bytes(self::API_KEY_BYTES));
        $apiSecret = bin2hex(random_bytes(self::API_SECRET_BYTES));

        if ($this->merchantRepository->findByApiKey($apiKey) !== null) {
            $io->error('Коллизия apiKey, попробуйте запустить команду ещё раз.');

            return Command::FAILURE;
        }

        $merchant = (new Merchant())
            ->setName($name)
            ->setApiKey($apiKey)
            ->setApiSecret($apiSecret)
            ->setBaseCurrency($baseCurrency)
            ->setStatus(Status::Active);

        $this->entityManager->persist($merchant);
        $this->entityManager->flush();

        $io->section('Мерчант создан');
        $io->table(
            ['Поле', 'Значение'],
            [
                ['ID', (string) $merchant->getId()],
                ['Name', $merchant->getName()],
                ['Base currency', $merchant->getBaseCurrency()],
                ['Status', $merchant->getStatus()->value],
                ['API key', $merchant->getApiKey()],
            ],
        );

        $io->newLine();
        $io->warning('API secret будет показан только один раз. Сохраните его сразу.');
        $io->writeln(sprintf('<info>%s</info>', $apiSecret));
        $io->newLine();

        return Command::SUCCESS;
    }

    private function promptOrArgument(
        SymfonyStyle $io,
        InputInterface $input,
        string $question,
        mixed $value,
        ?string $default = null,
    ): ?string {
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        if (!$input->isInteractive()) {
            return null;
        }

        return $default === null
            ? $io->ask($question)
            : $io->ask($question, $default);
    }
}
