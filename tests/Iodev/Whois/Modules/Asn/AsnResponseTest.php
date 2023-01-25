<?php

declare(strict_types=1);

namespace Iodev\Whois\Modules\Asn;

use PHPUnit\Framework\TestCase;

class AsnResponseTest extends TestCase
{
    /** @var AsnResponse */
    private $resp;

    public function setUp(): void
    {
        $this->resp = new AsnResponse(
            "AS32934",
            "whois.host.abc",
            "-i origin AS32934",
            "Test content",
        );
    }

    public function testGetAsn()
    {
        self::assertEquals("AS32934", $this->resp->asn);
    }

    public function testGetQuery()
    {
        self::assertEquals("-i origin AS32934", $this->resp->query);
    }

    public function testGetText()
    {
        self::assertEquals("Test content", $this->resp->text);
    }

    public function testGetHost()
    {
        self::assertEquals("whois.host.abc", $this->resp->host);
    }
}