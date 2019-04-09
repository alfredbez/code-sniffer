<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;

/**
 * Verifies that a `@return` tag exists for all functions and methods and that it does not exist
 * for all constructors and destructors.
 *
 * @author Mark Scherer
 * @license MIT
 */
class DocBlockReturnTagSniff extends AbstractScopeSniff
{
    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct([T_CLASS], [T_FUNCTION]);
    }

    /**
     * @inheritDoc
     */
    protected function processTokenWithinScope(File $phpcsFile, $stackPtr, $currScope)
    {
        $tokens = $phpcsFile->getTokens();

        $method = $phpcsFile->findNext(T_STRING, ($stackPtr + 1));
        $returnRequired = !in_array($tokens[$method]['content'], ['__construct', '__destruct']);

        $find = [
        T_COMMENT,
        T_DOC_COMMENT,
        T_CLASS,
        T_FUNCTION,
        T_OPEN_TAG,
        ];

        $commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1));

        if ($commentEnd === false) {
            return;
        }

        if ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT) {
            return;
        }

        $commentStart = ($phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1);

        $commentWithReturn = null;
        for ($i = $commentEnd; $i >= $commentStart; $i--) {
            $currentComment = $tokens[$i]['content'];
            if (strpos($currentComment, '@return ') !== false) {
                $commentWithReturn = $i;
                break;
            }
        }

        if (!$commentWithReturn && !$returnRequired) {
            return;
        }

        if ($commentWithReturn && $returnRequired) {
            return;
        }

        // A class method should have @return
        if (!$commentWithReturn) {
            $error = 'Missing @return tag in function comment';
            $phpcsFile->addError($error, $stackPtr, 'Missing');

            return;
        }

        // Constructor/destructor should not have @return
        if ($commentWithReturn) {
            $error = 'Unexpected @return tag in constructor/destructor comment';
            $fixable = $phpcsFile->addFixableError($error, $commentWithReturn, 'Unexpected');
            if ($fixable) {
                $phpcsFile->fixer->replaceToken($commentWithReturn, '');
            }

            return;
        }
    }

    /**
     * @inheritDoc
     */
    protected function processTokenOutsideScope(File $phpcsFile, $stackPtr)
    {
    }
}
