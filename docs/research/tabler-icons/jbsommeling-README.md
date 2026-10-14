# Blade Tabler Icons

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jbsommeling/blade-tabler-icons.svg?style=flat-square)](https://packagist.org/packages/jbsommeling/blade-tabler-icons)
[![Total Downloads](https://img.shields.io/packagist/dt/jbsommeling/blade-tabler-icons.svg?style=flat-square)](https://packagist.org/packages/jbsommeling/blade-tabler-icons)
[![Monthly Downloads](https://img.shields.io/packagist/dm/jbsommeling/blade-tabler-icons.svg?style=flat-square)](https://packagist.org/packages/jbsommeling/blade-tabler-icons)
[![PHP Version](https://img.shields.io/packagist/php-v/jbsommeling/blade-tabler-icons.svg?style=flat-square)](https://packagist.org/packages/jbsommeling/blade-tabler-icons)
[![License](https://img.shields.io/packagist/l/jbsommeling/blade-tabler-icons.svg?style=flat-square)](https://packagist.org/packages/jbsommeling/blade-tabler-icons)

A package to easily make use of [Tabler Icons](https://github.com/tabler/tabler-icons) in your Laravel Blade views. This is forked from [ryangjchandler](https://github.com/ryangjchandler/blade-tabler-icons) as the package was outdated and the one he is referring to was missing some icons. 


For a full list of available icons see [the SVG directory](resources/svg) or preview them on [GitHub](https://preview.tabler.io/icons.html).

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12

## Installation

```bash
composer require jbsommeling/blade-tabler-icons
```

## Usage

Icons can be used a self-closing Blade components which will be compiled to SVG icons:

```blade
<x-tabler-alert-circle />
```

You can also pass classes to your icon components:

```blade
<x-tabler-alert-circle class="w-6 h-6 text-gray-500"/>
```

And even use inline styles:

```blade
<x-tabler-alert-circle style="color: #555"/>
```

### Raw SVG Icons

If you want to use the raw SVG icons as assets, you can publish them using:

```bash
php artisan vendor:publish --tag=blade-tabler-icons --force
```

Then use them in your views like:

```blade
<img src="{{ asset('vendor/blade-tabler-icons/alert-circle.svg') }}" width="10" height="10"/>
```

### Blade Icons

Blade Tabler Icons uses Blade Icons under the hood. Please refer to [the Blade Icons readme](https://github.com/blade-ui-kit/blade-icons) for additional functionality.

## Maintainers

Blade Tabler Icons is developed and maintained by [J.B. Sommeling](https://github.com/JBSommeling/)

## License

Blade Tabler Icons is open-sourced software licensed under [the MIT license](LICENSE.md).
