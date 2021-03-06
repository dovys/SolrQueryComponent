<?php
namespace InterNations\Component\Solr\Expression;

use InterNations\Component\Solr\Util;

/**
 * Wildcard expression class
 *
 * Wildcard expression class is used to generate queries with wildcard expressions in the like of <prefix>*,
 * <prefix>*<suffix>, <prefix>? or <prefix>?<suffix>.
 */
class WildcardExpression extends Expression
{
    /**
     * Wildcard character
     *
     * @var string
     */
    protected $wildcard;

    /**
     * Wildcard query prefix
     *
     * @var string|Expression
     */
    protected $prefix = '';

    /**
     * Wildcard query suffix
     *
     * @var string|Expression
     */
    protected $suffix;

    /**
     * Create new wildcard query object
     *
     * @param string $wildcard
     * @param string|Expression $prefix
     * @param string|Expression $suffix
     */
    public function __construct($wildcard, $prefix = '', $suffix = null)
    {
        $this->wildcard = $wildcard === '*' ? '*' : '?';
        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }

    /**
     * @SuppressWarnings(PMD.NPathComplexity)
     * @SuppressWarnings(PMD.CyclomaticComplexity)
     *
     * @return string
     */
    public function  __toString()
    {
        if ($this->prefix instanceof PhraseExpression) {
            $prefix = substr($this->prefix, 0, -1);
            $phrasePrefix = true;
        } else {
            $prefix = Util::escape($this->prefix);
            $phrasePrefix = false;
        }

        if ($this->suffix instanceof PhraseExpression) {
            $suffix = substr($this->suffix, 1);
            $phraseSuffix = true;
        } else {
            $suffix = Util::escape($this->suffix);
            $phraseSuffix = false;
        }

        $expr = (!$phrasePrefix && $phraseSuffix) ? '"' : '';
        $expr .= $prefix;
        $expr .= $this->wildcard;
        $expr .= ($phrasePrefix && !$phraseSuffix && !$suffix) ? '"' : '';
        $expr .= $suffix;
        $expr .= ($phrasePrefix && !$phraseSuffix && $suffix) ? '"' : '';

        return $expr;
    }
}
