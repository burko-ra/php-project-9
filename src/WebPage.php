<?php

namespace PageAnalyzer;

class WebPage
{
    protected \DiDom\Document $document;

    public function __construct(string $html)
    {
        $this->document = new \DiDom\Document($html);
    }

    /**
     * @return string|null
     */
    public function getFirstTagInnerText(string $tag)
    {
        $matches = $this->document->find($tag);
        if (count($matches) > 0) {
            /**
             * @var \DiDom\Element $firstTag
             */
            $firstTag = $matches[0];
            return $firstTag->text();
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getDescription()
    {
        $metaDescriptionMatches = $this->document->find('meta[name=description]');
        if (count($metaDescriptionMatches) > 0) {
            $firstTag = $metaDescriptionMatches[0];
            return $firstTag->getAttribute('content') ?? '';
        }
        return null;
    }
}
