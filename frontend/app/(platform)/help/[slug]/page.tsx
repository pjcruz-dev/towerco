import { HelpGuidePageClient } from "./help-guide-page-client";

export default async function HelpGuidePage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  return <HelpGuidePageClient slug={slug} />;
}
