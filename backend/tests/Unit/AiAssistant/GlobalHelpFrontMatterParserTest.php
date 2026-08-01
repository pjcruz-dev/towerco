<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\GlobalHelpFrontMatterParser;
use App\Modules\AiAssistant\Services\KnowledgeCatalogService;
use Tests\TestCase;

final class GlobalHelpFrontMatterParserTest extends TestCase
{
    public function test_parses_article_frontmatter_and_body(): void
    {
        $raw = <<<'MD'
---
title: Sample Guide
slug: sample-guide
module: core
audience: tenant_user
permissions:
  - dashboard:view
status: published
version: 2
related_routes:
  - /dashboard
last_reviewed: 2026-07-17
---

# Sample Guide

Body content here.
MD;

        $article = app(GlobalHelpFrontMatterParser::class)->parse(
            $raw,
            '/tmp/sample-guide.md',
            'Knowledge/global/sample-guide.md',
        );

        $this->assertSame('sample-guide', $article->slug);
        $this->assertSame('Sample Guide', $article->title);
        $this->assertSame('core', $article->moduleKey);
        $this->assertSame(['dashboard:view'], $article->permissions);
        $this->assertSame(['/dashboard'], $article->relatedRoutes);
        $this->assertSame(2, $article->version);
        $this->assertStringContainsString('Body content here.', $article->body);
        $this->assertNotSame('', $article->contentChecksum);
    }

    public function test_discovers_packaged_global_articles(): void
    {
        $articles = app(KnowledgeCatalogService::class)->discoverGlobalArticles();

        $this->assertGreaterThanOrEqual(11, $articles->count());
        $this->assertTrue($articles->contains(fn ($article) => $article->slug === 'getting-started'));
        $this->assertTrue($articles->contains(fn ($article) => $article->slug === 'e-approval-create-request'));
        $this->assertTrue($articles->contains(fn ($article) => $article->moduleKey === 'document_register'));
    }
}
