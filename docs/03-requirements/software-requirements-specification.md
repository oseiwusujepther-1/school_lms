# Software Requirements Specification

## Document status

**Draft for stakeholder review.** This specification is based on the project proposal and has not yet been validated with students, teachers, or other stakeholders. The system has no implementation yet.

## 1. Purpose

This document defines the proposed initial scope and quality expectations for School LMS. Detailed user workflows and acceptance criteria are maintained in [feature-requirements.md](feature-requirements.md); unresolved questions are tracked in [elicitation-notes.md](elicitation-notes.md).

## 2. Product scope

School LMS is intended to provide a shared place for students to access course materials and assignments, submit work, and for teachers to organize materials and review submissions.

The proposed first increment includes:

- Student and teacher roles with role-appropriate access.
- Course materials organized by course.
- Assignments published for courses.
- Student submissions and teacher review of those submissions.

Live video lessons, payment processing, and SMS notifications are outside the initial scope. No external service, technology stack, or deployment environment has been selected.

## 3. Intended users

- **Students** access materials and assignments for their courses and submit assigned work.
- **Teachers** organize course materials, publish assignments, and review student submissions.

School administrators and other user types are not in scope unless stakeholders identify a requirement for them.

## 4. Operating assumptions and constraints

- This is a proposed web-based learning management system, but the platform and supported devices have not been confirmed.
- Users, courses, and role assignments must be available to support the proposed workflows; account creation and enrollment processes remain undecided.
- Supported material and submission formats, file-size limits, and storage arrangements remain undecided.
- Requirements and priorities may change as stakeholder feedback is gathered.

## 5. Functional requirements

The system shall satisfy the proposed functional requirements **FR-01 through FR-06** in [feature-requirements.md](feature-requirements.md). The IDs are used for traceability across requirements and tests. They remain draft until reviewed and approved by stakeholders.

## 6. Quality requirements and constraints

The following expectations apply to the proposed scope. Values that depend on product, hosting, or policy decisions are explicitly left open rather than assumed.

| ID | Requirement | Verification / decision needed |
|----|-------------|-------------------------------|
| NFR-01 | The system shall restrict course materials and student submissions to users authorized for the relevant course and role. | Test access as a student outside the course and as a teacher without responsibility for the course; both must be denied. Confirm teacher-course assignment rules. |
| NFR-02 | The system shall give users a clear outcome when they attempt to publish an assignment or submit work. | Test successful and unsuccessful actions; the user must be told whether the action succeeded and what to do if it failed. |
| NFR-03 | The main student and teacher workflows shall be usable by the intended users and accessible. | Select an accessibility standard and usability evaluation method with stakeholders before interface acceptance. |
| NFR-04 | The system shall handle personal and student-submission data according to agreed privacy, access, and retention rules. | Confirm applicable policy, data retention period, and account/data deletion process before implementation. |
| NFR-05 | The system shall meet agreed performance, availability, and recovery expectations for its intended deployment. | Set measurable targets after the expected user load and hosting approach are known. |

NFR-03 through NFR-05 require stakeholder and technical decisions before they can be considered complete, testable commitments.

## 7. External interfaces

No external integrations are currently required or selected. Confirm whether the system needs integrations for identity, file storage, notifications, or other school services before adding them to scope.

## 8. Acceptance and change control

A requirement is ready for implementation when its wording is understood, its priority is agreed, dependencies and assumptions are recorded, and its acceptance criteria can be tested. The team should review proposed changes with stakeholders, update the affected requirement and acceptance criteria, and keep the feature and test references aligned.

## 9. Open decisions

Resolve the questions in [elicitation-notes.md](elicitation-notes.md) before treating this draft as a baseline or committing to unresolved quality targets.
