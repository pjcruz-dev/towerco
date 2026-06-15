SELECT fm.name, s.step_order, s.approver_type, s.approver_id
FROM e_approval_workflow_steps s
JOIN e_approval_workflow_templates t ON t.id = s.template_id
JOIN e_approval_forms fm ON fm.id = t.form_id
WHERE fm.name LIKE '%Payment%';
