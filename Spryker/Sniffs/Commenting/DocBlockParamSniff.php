<?php

namespace Spryker\Sniffs\Commenting;

use PHP_CodeSniffer_File;
use Spryker\Sniffs\AbstractSniffs\AbstractSprykerSniff;
use Spryker\Tools\Traits\CommentingTrait;
use Spryker\Tools\Traits\SignatureTrait;

/**
 * Makes sure doc block param types match the variable name of the method signature.
 *
 * @author Mark Scherer
 * @license MIT
 */
class DocBlockParamSniff extends AbstractSprykerSniff
{

    use CommentingTrait;
    use SignatureTrait;

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [
            T_FUNCTION,
        ];
    }

    /**
     * @inheritDoc
     */
    public function process(PHP_CodeSniffer_File $phpCsFile, $stackPointer)
    {
        $tokens = $phpCsFile->getTokens();

        $docBlockEndIndex = $this->findRelatedDocBlock($phpCsFile, $stackPointer);

        if (!$docBlockEndIndex) {
            return;
        }

        $docBlockStartIndex = $tokens[$docBlockEndIndex]['comment_opener'];

        if ($this->hasInheritDoc($phpCsFile, $docBlockStartIndex, $docBlockEndIndex)) {
            return;
        }

        $methodSignature = $this->getMethodSignature($phpCsFile, $stackPointer);
        if (!$methodSignature) {
            return;
        }

        $docBlockParams = [];
        for ($i = $docBlockStartIndex + 1; $i < $docBlockEndIndex; $i++) {
            if ($tokens[$i]['type'] !== 'T_DOC_COMMENT_TAG') {
                continue;
            }
            if (!in_array($tokens[$i]['content'], ['@param'])) {
                continue;
            }

            $classNameIndex = $i + 2;

            if ($tokens[$classNameIndex]['type'] !== 'T_DOC_COMMENT_STRING') {
                $phpCsFile->addError('Missing type in param doc block', $i);
                continue;
            }

            $content = $tokens[$classNameIndex]['content'];

            $appendix = '';
            $spacePos = strpos($content, ' ');
            if ($spacePos) {
                $appendix = substr($content, $spacePos);
                $content = substr($content, 0, $spacePos);
            }

            preg_match('/\$[^\s]+/', $appendix, $matches);
            $variable = $matches ? $matches[0] : '';

            $docBlockParams[] = [
                'index' => $classNameIndex,
                'type' => $content,
                'variable' => $variable,
                'appendix' => $appendix,
            ];
        }

        if (count($docBlockParams) !== count($methodSignature)) {
            $phpCsFile->addError('Doc Block params do not match method signature', $stackPointer);
            return;
        }

        foreach ($docBlockParams as $docBlockParam) {
            $methodParam = array_shift($methodSignature);
            $variableName = $tokens[$methodParam['variable']]['content'];

            if ($docBlockParam['variable'] === $variableName) {
                continue;
            }
            // We let other sniffers take care of missing type for now
            if (strpos($docBlockParam['type'], '$') !== false) {
                continue;
            }

            $error = 'Doc Block param variable `' . $docBlockParam['variable'] . '` should be `' . $variableName . '`';
            // For now just report (buggy yet)
            $phpCsFile->addError($error, $docBlockParam['index'], 'VariableWrong');
        }
    }

}
