# Easy link generator for Objects

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ntriga/pimcore-link-generator.svg?style=flat-square)](https://packagist.org/packages/ntriga/pimcore-link-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/ntriga/pimcore-link-generator.svg?style=flat-square)](https://packagist.org/packages/ntriga/pimcore-link-generator)

Generate urls for objects using the NtrigaLinkGenerator.  
The links will have this format: 
``` 
.../_locale/{document_path}/{unique_id_letter}{objectId}/{objectSlug}
```

## Reasoning Behind URL Format
- Based on the ID, we can load the page a bit faster, which is important for UX and SEO.
- The length of the path has no importance for SEO, and the path is only used as an index. For this, we provide canonical URLs.
- In the SERP, the path is no longer displayed; instead, breadcrumbs are shown.
- The title/slug appears at the end of the path instead of the ID, making it clearer for the user.

## Installation

You can install the package via composer:

```bash
composer require ntriga/pimcore-link-generator
```

## Usage
Follow these steps to use the link generator.

### Create property on the home document
Create a property on the home document of the type "document" that is inheritable.  
This property will store the parent page of the object.    
This makes it easy to link another document for each language.

E.g. "news_document"
![document settings.png](docs%2Fimages%2Fdocument%20settings.png)

### Create an action with the correct annotation
Create an action that will be used to generate the link.  
The annotation should be this format
```php
/**
* @Route("{path}/n{objectId}/{objectSlug}", name="news-detail", defaults={"path"=""}, requirements={"path"=".*?", "objectSlug"="[\w-]+", "objectId"="\d+"})
*/
```

### Create LinkGenerator class for the object

```php
namespace Ntriga\FrontBundle\LinkGenerator;

use Ntriga\PimcoreLinkGenerator\NtrigaLinkGenerator;
use Pimcore\Model\DataObject\News;

class NewsLinkGenerator extends NtrigaLinkGenerator
{
    /*
     * Here you can set the name of the property that is used to store the parent document
     */
    protected function getDefaultDocumentPropertyName(): string
    {
        return 'news_document';
    }

    /*
     * Here you can set the class name of the object
     */
    protected function getObjectClassName(): string
    {
        return News::class;
    }
    
    /*
     * Here you can set the route name of the action that will be used to generate the link
     */
    protected function getRouteName(): string
    {
        return 'news-detail';
    }
    
    /*
     * Optional
     * Here you can set the name of the method that will be used to generate the slug
     * Default is "getName"
     * If you have a slug input field, this will be used instead
     */
    protected function getObjectDefaultSlugField(): string
    {
        return 'getTitle';
    }
}
```

### Register the LinkGenerator as a service
Add the LinkGenerator as a service in your services.yaml file.
```yaml
services:
    Ntriga\FrontBundle\LinkGenerator\NewsLinkGenerator:
        public: true
```

### Create a twig extension
Create a twig extension that will be used to generate the link.
```php
<?php

namespace Ntriga\FrontBundle\Twig\Extension;

use Ntriga\FrontBundle\LinkGenerator\NewsLinkGenerator;
use Pimcore\Model\DataObject\News;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NewsExtension extends AbstractExtension
{
    public function __construct(
        protected NewsLinkGenerator $newsLinkGenerator
    ) {}

    public function getFunctions()
    {
        return [
            new TwigFunction('app_news_detaillink', [$this, 'generateLink']),
        ];
    }

    public function generateLink(News $item): string
    {
        return $this->newsLinkGenerator->generate($item, []);
    }
}
```

### Register the twig extension as a service
Add the twig extension as a service in your services.yaml file.
```yaml
services:
    Ntriga\FrontBundle\Twig\Extension\NewsExtension:
        tags: ['twig.extension']
```

### Use the twig extension in your templates

```twig
<a href="{{ app_news_detaillink(newsItem) }}">News detail</a>
```

### Make sure the detail page contains a canonical link

With this strategy, the detail page will be accessible via multiple URLs.
So it's important you set a canonical link on the detail page.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Vincent Bibauw](https://github.com/VincentBibauw)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
