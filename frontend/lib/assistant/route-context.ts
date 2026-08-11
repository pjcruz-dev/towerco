export type AssistantRouteContext = {
  moduleKey: string | null;
  pagePath: string;
  suggestedQuestions: string[];
};

const DEFAULT_SUGGESTIONS = [
  "How do I get started in TowerOS?",
  "Why can’t I see a page or module?",
  "How do I create an E-Approval request?",
];

const ROUTE_RULES: Array<{
  match: RegExp;
  moduleKey: string;
  suggestions: string[];
}> = [
  {
    match: /^\/e-approval(\/|$)/,
    moduleKey: "e_approval",
    suggestions: [
      "How do I create an E-Approval request?",
      "How do I approve a request waiting for me?",
      "Where do I find my submissions?",
    ],
  },
  {
    match: /^\/ticketing(\/|$)/,
    moduleKey: "ticketing",
    suggestions: [
      "How do I create a ticket?",
      "What is the status of TKT-00001?",
      "Who can assign tickets?",
    ],
  },
  {
    match: /^\/procurement\/grns(\/|$)/,
    moduleKey: "procurement_one",
    suggestions: [
      "How do I raise a ticket for a GRN mismatch?",
      "How do I record a goods receipt?",
      "How does the purchase order workflow work?",
    ],
  },
  {
    match: /^\/procurement\/pos(\/|$)/,
    moduleKey: "procurement_one",
    suggestions: [
      "How does the purchase order workflow work?",
      "How do I track a delayed delivery on a PO?",
      "How do I raise a ticket from a purchase order?",
    ],
  },
  {
    match: /^\/procurement(\/|$)/,
    moduleKey: "procurement_one",
    suggestions: [
      "How does the purchase order workflow work?",
      "How do I raise a ticket for a GRN mismatch?",
      "What is Procurement-One?",
    ],
  },
  {
    match: /^\/sites(\/|$)/,
    moduleKey: "sites",
    suggestions: [
      "How do I find a site by site code?",
      "What is linked to a site in TowerOS?",
    ],
  },
  {
    match: /^\/documents\/controlled(\/|$)/,
    moduleKey: "document_register",
    suggestions: [
      "How do I use the document register?",
      "How do I find the current controlled revision?",
    ],
  },
  {
    match: /^\/documents(\/|$)/,
    moduleKey: "documents",
    suggestions: [
      "How do I upload a document to a site binder?",
      "Where do I track expiring documents?",
    ],
  },
  {
    match: /^\/project-one(\/|$)/,
    moduleKey: "project_one",
    suggestions: [
      "How do I find a rollout?",
      "How do gate approvals work?",
    ],
  },
  {
    match: /^\/users(\/|$)/,
    moduleKey: "team_access",
    suggestions: [
      "How do I assign roles to a user?",
      "Why can’t a user see a module?",
    ],
  },
  {
    match: /^\/dashboard(\/|$)/,
    moduleKey: "core",
    suggestions: DEFAULT_SUGGESTIONS,
  },
];

export function resolveAssistantRouteContext(pathname: string | null | undefined): AssistantRouteContext {
  const pagePath = pathname && pathname !== "" ? pathname : "/dashboard";

  for (const rule of ROUTE_RULES) {
    if (rule.match.test(pagePath)) {
      return {
        moduleKey: rule.moduleKey,
        pagePath,
        suggestedQuestions: rule.suggestions,
      };
    }
  }

  return {
    moduleKey: "core",
    pagePath,
    suggestedQuestions: DEFAULT_SUGGESTIONS,
  };
}
