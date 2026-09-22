<?php

namespace App\Elavon\Converge2\DataObject;

final class HppType extends AbstractEnum
{
    const FULL_PAGE_REDIRECT = 'fullPageRedirect';

    const LIGHTBOX = 'lightbox';

    const HOSTED_PAYMENT_FIELDS = 'hostedPaymentFields';

    const PAYMENT_LINK = 'paymentLink';

    public function isFullPageRedirect()
    {
        return $this->getValue() == self::FULL_PAGE_REDIRECT;
    }

    public function isLightbox()
    {
        return $this->getValue() == self::LIGHTBOX;
    }

    public function isHostedPaymentFields()
    {
        return $this->getValue() == self::HOSTED_PAYMENT_FIELDS;
    }

    public function isPaymentLink()
    {
        return $this->getValue() == self::PAYMENT_LINK;
    }
}
