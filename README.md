# Zana Configuration

Configuration management for the Zana framework.

## Features

- **`ConfigurationInterface`** / **`ConfigurationRepository`** — hierarchical key-value store with dot-notation support
- **`ArrayLoader`** — load configuration from PHP arrays
- **`FileLoader`** — load configuration from PHP config files
- **`EnvironmentLoader`** — load configuration from environment variables
- **`ConfigurationException`** — typed error handling

## Install

```
composer require zana/configuration
```

## Usage

```php
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Loaders\ArrayLoader;

$repo = new ConfigurationRepository();
$repo->addLoader(new ArrayLoader(['app' => ['name' => 'Zana']]));
$repo->set('database.host', '127.0.0.1');

echo $repo->get('app.name');      // Zana
echo $repo->get('database.host'); // 127.0.0.1
```

## Testing

```
vendor/bin/phpunit packages/configuration/tests
```

## License

MIT — see [LICENSE](LICENSE).

