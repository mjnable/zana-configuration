# Zana Configuration

Configuration from PHP files, `.env` files and environment variables, merged
into one immutable tree, with typed accessors and `#[Config]` injection.
Requires PHP 8.5 or later.

```
composer require mj-zana/configuration
```

## Usage

```php
use Zana\Configuration\{ConfigurationBuilder, DotEnv};

DotEnv::load(__DIR__ . '/.env');          // never overrides real environment variables

$config = (new ConfigurationBuilder())
    ->addDirectory(__DIR__ . '/config')   // config/database.php → database.*
    ->addEnvironment()                     // APP_DATABASE__HOST=db → database.host
    ->build();

$config->get('database.connections.main.host', 'localhost');
$config->int('database.port');            // typed; throws on a missing key or wrong type
$config->section('database')->all();
```

Later sources win. Associative arrays merge recursively; lists and scalars are
replaced whole. Environment values `true`, `false`, `null` and numbers are
converted to their types.

## Injection

```php
use Zana\Configuration\{Config, ConfigValueProvider};

final class Mailer
{
    public function __construct(#[Config('mail.host')] private string $host, #[Config('mail.port')] private int $port = 25) {}
}

$services->addParameterProvider(new ConfigValueProvider($config));
```

`zana/core` registers the provider automatically.

## Caching

```php
ConfigurationCache::write($path, $config);   // atomic
$config = ConfigurationCache::load($path) ?? $builder->build();
```

The file is PHP, which `load()` runs. Pass a key to both (2.36) so that a file changed by anything but the
application does not run: `ConfigurationCache::write($path, $config, $key)`, `ConfigurationCache::load($path, $key)`.

## License

MIT — see [LICENSE](LICENSE).
