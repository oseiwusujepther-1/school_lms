# UML Diagrams

These diagrams are draft models of the proposed School LMS scope. They are based on the requirements in [`docs/03-requirements/feature-requirements.md`](../03-requirements/feature-requirements.md) and should be revised when stakeholder decisions clarify roles, enrollment, and submission rules.

## Diagrams

| Diagram | Purpose | Related requirements |
|---------|---------|----------------------|
| [Use-case diagram](use-cases.puml) | Shows the proposed student and teacher interactions with School LMS. | FR-01–FR-06 |
| [Domain model](domain-model.puml) | Shows the conceptual relationships between users, courses, materials, assignments, and submissions. | FR-01–FR-06 |
| [Submission sequence](submission-sequence.puml) | Shows the proposed success and failure flow when a student submits work. | FR-01, FR-05 |

The `.puml` files use PlantUML source syntax. Open them with a PlantUML-compatible editor or renderer to view the diagrams. No rendering tool is configured in this repository.

## Modeling notes

- The domain model is conceptual; it does not prescribe a database schema or implementation.
- Course enrollment, teacher-to-course assignment, authentication, and submission-attempt rules still need stakeholder decisions.
- The diagrams describe proposed behavior, not implemented functionality.
