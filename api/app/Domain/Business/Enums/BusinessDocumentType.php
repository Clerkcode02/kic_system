<?php

declare(strict_types=1);

namespace App\Domain\Business\Enums;

enum BusinessDocumentType: string
{
    case BusinessLicense = 'business_license';
    case TaxCertificate = 'tax_certificate';
    case GovernmentId = 'government_id';
    case InsuranceCertificate = 'insurance_certificate';
    case Other = 'other';
}
