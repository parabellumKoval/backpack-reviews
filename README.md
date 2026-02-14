# Backpack-reviews

[![Build Status](https://travis-ci.org/parabellumKoval/backpack-reviews.svg?branch=master)](https://travis-ci.org/parabellumKoval/backpack-reviews)
[![Coverage Status](https://coveralls.io/repos/github/parabellumKoval/backpack-reviews/badge.svg?branch=master)](https://coveralls.io/github/parabellumKoval/backpack-reviews?branch=master)

[![Packagist](https://img.shields.io/packagist/v/parabellumKoval/backpack-reviews.svg)](https://packagist.org/packages/parabellumKoval/backpack-reviews)
[![Packagist](https://poser.pugx.org/parabellumKoval/backpack-reviews/d/total.svg)](https://packagist.org/packages/parabellumKoval/backpack-reviews)
[![Packagist](https://img.shields.io/packagist/l/parabellumKoval/backpack-reviews.svg)](https://packagist.org/packages/parabellumKoval/backpack-reviews)

This package provides a quick starter kit for implementing reviews for Laravel Backpack. Provides a database, CRUD interface, API routes and more.

## Installation

Install via composer
```bash
composer require parabellumKoval/backpack-reviews
```

Migrate
```bash
php artisan migrate
```

### Publish

#### Configuration File
```bash
php artisan vendor:publish --provider="Backpack\Reviews\ServiceProvider" --tag="config"
```

#### Views File
```bash
php artisan vendor:publish --provider="Backpack\Reviews\ServiceProvider" --tag="views"
```

#### Migrations File
```bash
php artisan vendor:publish --provider="Backpack\Reviews\ServiceProvider" --tag="migrations"
```

#### Routes File
```bash
php artisan vendor:publish --provider="Backpack\Reviews\ServiceProvider" --tag="routes"
```

## Usage

### Seeders
```bash
php artisan db:seed --class="Backpack\Reviews\database\seeders\ReviewSeeder"
```

## Google Business Profile

See `GOOGLE_BUSINESS_PROFILE.md` for OAuth setup, sync commands, and API usage.

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [](https://github.com/parabellumKoval/backpack-reviews)
- [All contributors](https://github.com/parabellumKoval/backpack-reviews/graphs/contributors)
