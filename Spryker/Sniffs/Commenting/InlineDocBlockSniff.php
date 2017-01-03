<?php

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer_File;
use Spryker\Sniffs\AbstractSniffs\AbstractSprykerSniff;

/**
 * Checks if inline doc blocks have the correct order and format.
 */
class InlineDocBlockSniff extends AbstractSprykerSniff
{

    /**
     * @inheritdoc
     */
    public function register()
    {
        return [
            T_FUNCTION,
        ];
    }

    /**
     * @inheritdoc
     */
    public function process(PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();
        $startIndex = $phpCsFile->findNext(T_OPEN_CURLY_BRACKET, $stackPointer + 1);
        if (empty($tokens[$startIndex]['bracket_closer'])) {
            return;
        }

        $endIndex = $tokens[$startIndex]['bracket_closer'];

        $this->fixDocCommentOpenTags($phpCsFile, $startIndex, $endIndex);

        $this->checkInlineComments($phpCsFile, $startIndex, $endIndex);
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $startIndex
     * @param int $endIndex
     *
     * @return void
     */
    protected function fixDocCommentOpenTags(PHP_CodeSniffer_File $phpCsFile, $startIndex, $endIndex)
    {
        $tokens = $phpCsFile->getTokens();

        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            if ($tokens[$i]['code'] !== T_COMMENT) {
                continue;
            }

            if (!preg_match('|^\/\*\s*@\w+ (.+)|', $tokens[$i]['content'])) {
                continue;
            }

            $fix = $phpCsFile->addFixableError('Inline Doc Block comment should be using `/** ... */`', $i);
            if ($fix) {
                $phpCsFile->fixer->beginChangeset();

                $comment = $tokens[$i]['content'];
                $comment = str_replace('/*', '/**', $comment);

                $phpCsFile->fixer->replaceToken($i, $comment);

                $phpCsFile->fixer->endChangeset();
            }
        }
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $index
     *
     * @return void
     */
    protected function fixDocCommentCloseTags(PHP_CodeSniffer_File $phpCsFile, $index)
    {
        $tokens = $phpCsFile->getTokens();

        $content = $tokens[$index]['content'];
        if ($content === '*/') {
            return;
        }

        $fix = $phpCsFile->addFixableError('Inline Doc Block comment end tag should be `*/`, got `' . $content . '`', $index);
        if (!$fix) {
            return;
        }

        $phpCsFile->fixer->beginChangeset();

        $phpCsFile->fixer->replaceToken($index, '*/');

        $phpCsFile->fixer->endChangeset();
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $startIndex
     * @param int $endIndex
     *
     * @return void
     */
    protected function checkInlineComments(PHP_CodeSniffer_File $phpCsFile, $startIndex, $endIndex)
    {
        $tokens = $phpCsFile->getTokens();

        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            if ($tokens[$i]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
                continue;
            }

            $commentEndTag = $tokens[$i]['comment_closer'];
            $this->fixDocCommentCloseTags($phpCsFile, $commentEndTag);

            $typeTag = $this->_findTagIndex($tokens, $i, $commentEndTag, T_DOC_COMMENT_TAG);
            $contentTag = $this->_findTagIndex($tokens, $i, $commentEndTag, T_DOC_COMMENT_STRING);

            if ($typeTag === null || $contentTag === null) {
                $phpCsFile->addError('Invalid Inline Doc Block', $startIndex);
                return;
            }

            if ($tokens[$typeTag]['content'] !== '@var') {
                // We ignore those
                return;
            }

            $errors = $this->findErrors($phpCsFile, $contentTag);

            if (!$errors) {
                continue;
            }

            $fix = $phpCsFile->addFixableError('Invalid Inline Doc Block content: ' . implode(', ', $errors), $i);
            if (!$fix) {
                continue;
            }

            $phpCsFile->fixer->beginChangeset();

            $comment = $tokens[$contentTag]['content'];

            if (isset($errors['space-before-end']) || isset($errors['end'])) {
                $comment .= ' ';
            }

            if (isset($errors['order'])) {
                $comment = preg_replace('|^(.+?)\s+(.+?)\s*$|', '\2 \1 ', $comment);
            }

            $phpCsFile->fixer->replaceToken($contentTag, $comment);

            $phpCsFile->fixer->endChangeset();
        }
    }

    /**
     * @param array $tokens
     * @param int $from
     * @param int $to
     * @param int $tagType
     * @return int|null
     */
    protected function _findTagIndex(array $tokens, $from, $to, $tagType) {
        for ($i = $from + 1; $i < $to; $i++) {
            if ($tokens[$i]['code'] === $tagType) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param \PHP_CodeSniffer_File $phpCsFile
     * @param int $contentIndex
     *
     * @return array
     */
    protected function findErrors(PHP_CodeSniffer_File $phpCsFile, $contentIndex)
    {
        $tokens = $phpCsFile->getTokens();

        $comment = $tokens[$contentIndex]['content'];

        preg_match('|^(.+?)(\s+)(.+?)\s*$|', $comment, $contentMatches);
        if (!$contentMatches || !$contentMatches[1] || !$contentMatches[3]) {
            $phpCsFile->addError('Invalid Inline Doc Block content', $contentIndex);
            return [];
        }

        $errors = [];

        if (!preg_match('|([a-z0-9]) $|i', $comment)) {
            $errors['space-before-end'] = 'Expected single space before ´*/´';
        }

        if (!preg_match('|^\$[a-z0-9]+$|i', $contentMatches[3])) {
            $errors['order'] = 'Expected ´{Type} ${var}´, got `' . $contentMatches[1] . $contentMatches[2] . $contentMatches[3] . '`';
        }

        return $errors;
    }

}
