"use client";

import { Button } from "@/components/ui/button";

type Props = {
  questions: string[];
  onSelect: (question: string) => void;
  disabled?: boolean;
};

export function AssistantSuggestedQuestions({ questions, onSelect, disabled }: Props) {
  if (questions.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-muted-foreground">Suggested</p>
      <div className="flex flex-col gap-1.5">
        {questions.map((question) => (
          <Button
            key={question}
            type="button"
            variant="outline"
            disabled={disabled}
            onClick={() => onSelect(question)}
            className="h-auto justify-start whitespace-normal rounded-xl px-3 py-2 text-left text-sm font-normal"
          >
            {question}
          </Button>
        ))}
      </div>
    </div>
  );
}
