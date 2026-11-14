<?php

namespace App\Service;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the absolute URL encoded in a machine fiche QR code and renders it
 * server-side as SVG (no JavaScript, no GD extension).
 *
 * The encoded payload is exactly the fault declaration route of one machine:
 * `<base>/faults/new?machine=<id>`. The base comes from the DEFAULT_URI
 * environment variable (wired as `framework.router.default_uri`), so the QR
 * always points at the reachable host of the deployment instead of a value
 * hard-coded in a template. The real value is set in `.env.local`.
 */
final class QrCodeService
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $defaultUri,
    ) {
    }

    /**
     * The exact string encoded in the QR code.
     */
    public function faultDeclarationUrl(int $machineId): string
    {
        return rtrim($this->defaultUri, '/').$this->urlGenerator->generate('fault_new', ['machine' => $machineId]);
    }

    public function faultDeclarationSvg(int $machineId): string
    {
        return $this->svg($this->faultDeclarationUrl($machineId));
    }

    /**
     * Renders the given payload as raw SVG markup (not a base64 data URI).
     */
    public function svg(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            // An XML prolog is invalid inline in an HTML document.
            'svgAddXmlHeader' => false,
        ]);

        return (string) (new QRCode($options))->render($payload);
    }
}
