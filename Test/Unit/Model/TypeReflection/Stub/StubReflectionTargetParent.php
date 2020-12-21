<?php declare(strict_types=1);

namespace Hyva\Admin\Test\Unit\Model\TypeReflection\Stub;

class StubReflectionTargetParent
{
    /**
     * @return string
     */
    public function getMethodWithInheritedReturnAnnotation()
    {
        return '';
    }

    final public function getMethodWithSignatureFromParent(): int
    {
        return 0;
    }

    /**
     * @return int
     */
    final public function getMethodWithReturnAnnotationFromParent()
    {
        return 0;
    }

    /**
     * @return int
     */
    final public function getMethodWithAnnotationAndSignatureFromParent(): string
    {
        return "0";
    }
}
