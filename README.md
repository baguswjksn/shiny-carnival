### Install

```sh
git clone https://github.com/baguswjksn/shiny-carnival.git
```

```sh
cd shiny-carnival
```

```sh
composer install
```

```sh
mv .env.example .env
```

> setelah edit env bisa di migrate

```sh
php artisan migrate:fresh --seed
```

```sh
php artisan serve --host=127.0.0.1 --port=8000
```
