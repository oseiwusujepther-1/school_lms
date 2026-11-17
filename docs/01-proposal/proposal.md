# School LMS Project Proposal

## Status

This is an initial proposal for stakeholder review. The feature requirements below are a starting point, not approved or complete requirements. Detailed drafts are maintained in the [software requirements specification](../03-requirements/software-requirements-specification.md) and [feature requirements](../03-requirements/feature-requirements.md). Confirm the problem and priorities with students, teachers, and the project team before implementation.

## Project summary

School LMS is a proposed digital learning space that brings course materials, assignments, and student work together. It aims to help students stay informed about course activities and help teachers organize and follow up on learning work.

## Problem statement

**Problem hypothesis to validate:**

When course materials, assignment instructions, and student work are shared through separate channels, students may have difficulty finding what they need, while teachers may find it harder to organize course work and follow submissions. School LMS proposes one shared place for these activities. This is a problem hypothesis, not a confirmed finding; the project must validate it with intended users and learn which workflows matter most.

## Goals

- Give students a clear place to access materials and assignments for their courses.
- Help teachers organize course materials and assignment work.
- Make assignment submission and follow-up easier to understand.
- Establish appropriate access to course information and student work.

The first increment should be considered useful when students can access the materials and assignments for their courses, and teachers can publish course work and review submissions. Confirm these outcomes with stakeholders before treating them as project success measures.

## Intended users

- **Students:** Find learning materials and assignment instructions for their courses, and submit assigned work.
- **Teachers:** Organize course materials, publish assignments, and review student submissions.

School administrators and other user types are not included in the initial scope until stakeholders confirm a need for them.

## Proposed initial requirements

These draft requirements describe the intended first scope. The team should refine their details and priorities with stakeholders.

### Functional requirements

| ID | Requirement | Initial acceptance check |
|----|-------------|--------------------------|
| FR-01 | The system shall distinguish student and teacher users and provide actions appropriate to each role. | Student users cannot use teacher-only actions; teachers can use teaching actions only for courses they are authorized to manage. |
| FR-02 | The system shall let authorized teachers add and organize learning materials under a course. | An authorized teacher adds a material and sees it listed under the course; a teacher without course access cannot add it. |
| FR-03 | The system shall let students access materials for courses they are authorized to participate in. | A student can open materials for an authorized course but cannot access restricted materials for another course. |
| FR-04 | The system shall let authorized teachers create and publish assignments for a course. | An authorized teacher publishes an assignment with a title and instructions; it is visible to students authorized for that course. |
| FR-05 | The system shall let students submit work for assignments in courses they are authorized to access. | A successful submission is clearly confirmed; a failed submission is not presented as successful. |
| FR-06 | The system shall let authorized teachers review submissions for assignments in courses they manage. | A teacher can identify and open submissions for an assignment they manage but cannot access submissions for an unauthorized course. |

Submission formats, deadlines, late-submission behavior, feedback, and grading rules need stakeholder decisions before the related requirements can be finalized.

### Quality and data requirements to confirm

- **Privacy and access control:** Only authorized users should be able to access course materials and student submissions. Confirm account management, role assignment, and retention expectations.
- **Usability and accessibility:** The main student and teacher workflows should be understandable and accessible to the intended users. Agree on the accessibility standard and how it will be evaluated.
- **Reliability:** The system should clearly report whether important actions, such as opening or submitting work, succeeded or failed. Set availability and recovery targets after the hosting approach is known.
- **Data handling:** Confirm which user, course, material, assignment, and submission data must be stored, who can view or change it, and when it should be removed.

These are areas requiring requirements elicitation; they are not yet measurable service-level commitments. See the [SRS](../03-requirements/software-requirements-specification.md) for draft quality requirements.

## Initial scope

The proposed first increment focuses on role-appropriate access, course materials, assignments, submissions, and teacher review of submissions.

Live video lessons, payment processing, and SMS notifications are outside the initial scope. Reconsider them only after the core workflows have been validated.

## Assumptions and open questions

- The project is intended for students and teachers, but the school context and stakeholder representatives have not yet been identified.
- The project has no application code, selected technology stack, or chosen external service yet.
- Decide how users receive accounts and how students become associated with courses.
- Decide whether materials and submissions support text, file uploads, or both, and what file limits apply.
- Decide whether assignments need due dates, late submissions, teacher feedback, or grades in the first increment.
- Confirm privacy, accessibility, data retention, and deployment expectations with stakeholders.

## Proposed validation

Before implementation, discuss the problem statement and draft requirements with representative students and teachers if available. Record their feedback, resolve high-impact open questions, and update the prioritized backlog and acceptance criteria. Use the project's three-week iterative process to review and refine the proposal as the team learns more. Track questions and session outcomes in [elicitation notes](../03-requirements/elicitation-notes.md).
