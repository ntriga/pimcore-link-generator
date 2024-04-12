<?php

namespace Ntriga\PimcoreLinkGenerator;

use InvalidArgumentException;
use Pimcore\Bundle\SeoBundle\Sitemap\UrlGeneratorInterface;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\LinkGeneratorInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Service;
use Pimcore\Twig\Extension\Templating\PimcoreUrl;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\AsciiSlugger;

abstract class NtrigaLinkGenerator implements LinkGeneratorInterface
{
    public function __construct(
        private Service $documentService,
        private DocumentResolver $documentResolver,
        private RequestStack $requestStack,
        private PimcoreUrl $pimcoreUrl,
        private LocaleServiceInterface $localeService,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    abstract protected function getDefaultDocumentPropertyName(): string;
    abstract protected function getObjectClassName(): string;
    abstract protected function getRouteName(): string;

    protected function getObjectDefaultSlugField(): string{
        return 'getName';
    }

    public function generate(object $object, array $params = []): string
    {
        $this->validateObjectClass($object);

        $locale = $this->getLocale($params);
        $document = $this->getDocumentForPath($locale, $params['document'] ?? null);
        $fullPath = $this->getFullPath($document, $locale);
        $fullPath = $this->addParentPaths($fullPath, $object, $params);
        $slug = $this->getSlug($object);

        return $this->generateUrl($slug, $object->getId(), $fullPath, $locale, $params);
    }

    protected function validateObjectClass(object $object): void
    {
        if (!is_a($object, $this->getObjectClassName())) {
            throw new InvalidArgumentException("Given object is not an instance of {$this->getObjectClassName()}");
        }
    }

    protected function getLocale(array $params): string
    {
        return $params['_locale'] ?? $this->localeService->getLocale();
    }

    protected function getFullPath(Document $document, string $locale): string
    {
        $localeUrlPart = '/' . $locale;
        if ($localeUrlPart !== $document->getFullPath()) {
            return substr($document->getFullPath(), strlen($localeUrlPart) + 1);
        }
        return '';
    }

    protected function addParentPaths(string $fullPath, object $object, array $params): string
    {
        $parent = $params['parent'] ?? $object->getParent();
        if ($parent) {
            $parentPaths = [];
            do {
                if ($parent->getType() == "folder") {
                    break;
                }
                $parentPaths[] = $this->getSlug($parent);
            } while ($parent = $parent->getParent());

            if ($parentPaths) {
                $fullPath .= '/' . implode('/', array_reverse($parentPaths));
            }
        }
        return $fullPath;
    }

    protected function getDocumentForPath(string $locale, ?Document $document = null){
        // If no document is provided, use the document of the current request
        if( !$document && $this->requestStack->getCurrentRequest()){
            $document = $this->documentResolver->getDocument($this->requestStack->getCurrentRequest());
        }

        // If there is no document (e.g. preview tab) use the root of the requested langauge
        if( !$document  ){
            $document = Document::getByPath('/'.$locale);
        }

        if( $document->getProperty('language') != $locale ){
            $documentToLookForTranslations = $document;
            $document = null;
            do {
                $translations = $this->documentService->getTranslations($documentToLookForTranslations);

                if( isset( $translations[$locale] ) ){
                    $document = Document::getById($translations[$locale]);
                }
                $documentToLookForTranslations = $documentToLookForTranslations->getParent();
            } while( $documentToLookForTranslations && !$document );

            // Fallback to home document for locale
            if( !$document ){
                $document = Document::getByPath('/'.$locale);
            }
        }

        $defaultDocumentPropertyName = $this->getDefaultDocumentPropertyName();
        if( !$defaultDocument = $document->getProperty($defaultDocumentPropertyName)){
            throw new InvalidArgumentException('Document has no property ' . $defaultDocumentPropertyName);
        }

        return $defaultDocument;
    }

    protected function getSlug(object $object): string
    {
        $slugger = new AsciiSlugger();

        $slugMethod = $this->getObjectDefaultSlugField();

        if (method_exists($object, 'getSlug')) {
            $slug = $object->getSlug();
        }

        if (empty($slug) && method_exists($object, $slugMethod)) {
            $slug = $object->{$slugMethod}();
            $slug = strtolower($slug);
        }

        if (empty($slug)) {
            throw new InvalidArgumentException('The object lacks a getSlug or ' . $slugMethod . ' method.');
        }

        return $slugger->slug($slug)->toString();
    }


    protected function generateUrl(string $slug, int $objectId, string $fullPath, string $locale, array $params): string
    {
        $url = $this->pimcoreUrl->__invoke(
            [
                'objectSlug' => strtolower($slug),
                'objectId' => $objectId,
                'path' => strtolower($fullPath),
                '_locale' => strtolower($locale),
            ],
            $this->getRouteName(),
            true
        );

        if (isset($params['referenceType']) && $params['referenceType'] === 0){
            $url = $this->urlGenerator->generateUrl($url);
        }

        return $url;
    }
}
