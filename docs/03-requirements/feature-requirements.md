# Feature Requirements

## Document status

These are proposed requirements for the first School LMS increment, derived from the project proposal. They are **drafts for stakeholder validation**, not confirmed commitments. Each requirement has an acceptance check to guide refinement and testing.

## FR-01: Student and teacher roles

**Requirement:** The system shall distinguish student and teacher users and provide actions appropriate to each role.

**Acceptance criteria:**

- Given a user with the student role, teacher-only actions are unavailable.
- Given a user with the teacher role, the user can access teaching actions for courses they are authorized to manage.
- A user who is not authorized for a course cannot access that course's restricted information.

**Open decisions:** How accounts are created, how roles are assigned, and how teacher-course authorization is managed.

## FR-02: Organize course materials

**Requirement:** The system shall let an authorized teacher add learning materials to a course and view the materials organized under that course.

**Acceptance criteria:**

- A teacher authorized for a course can add a material with the required details.
- The added material appears in the list for that course.
- A teacher who is not authorized for that course cannot add materials to it.

**Open decisions:** Material formats, required metadata, and whether materials are uploaded or linked.

## FR-03: Access course materials

**Requirement:** The system shall let students access learning materials for courses they are authorized to participate in.

**Acceptance criteria:**

- A student can find and open materials for an authorized course.
- A student cannot view restricted materials for a course they are not authorized to access.
- If a material cannot be opened, the system provides a clear indication that access failed.

**Open decisions:** How enrollment is represented and whether materials can be downloaded or viewed only in the system.

## FR-04: Publish course assignments

**Requirement:** The system shall let an authorized teacher create and publish an assignment for a course.

**Acceptance criteria:**

- A teacher authorized for a course can create an assignment with a title and instructions.
- After successful publication, the assignment is visible to students authorized for that course.
- A teacher who is not authorized for that course cannot publish an assignment there.

**Open decisions:** Due dates, scheduling, supported attachments, editing after publication, and late-work rules.

## FR-05: Submit assignment work

**Requirement:** The system shall let a student submit work for an assignment in a course they are authorized to access.

**Acceptance criteria:**

- A student can submit work for an available assignment in an authorized course.
- The system clearly confirms a successful submission.
- If submission fails, the system explains that it was not received and does not show a success confirmation.
- A student cannot submit work to an assignment in a course they are not authorized to access.

**Open decisions:** Text or file submissions, file limits, resubmissions, due dates, late submissions, and whether students can view their submitted work.

## FR-06: Review assignment submissions

**Requirement:** The system shall let an authorized teacher review submissions for assignments in courses they manage.

**Acceptance criteria:**

- A teacher can identify which authorized students have submitted work for an assignment.
- A teacher can open a submission for an assignment in a course they are authorized to manage.
- A teacher cannot access submissions for a course they are not authorized to manage.

**Open decisions:** Whether the first increment includes written feedback, grades, submission status labels, or export.

## Requirement traceability

| Requirement | Proposal | Related quality requirement |
|-------------|----------|-----------------------------|
| FR-01 | Proposed initial requirement FR-01 | NFR-01, NFR-04 |
| FR-02 | Proposed initial requirement FR-02 | NFR-01, NFR-02 |
| FR-03 | Proposed initial requirement FR-03 | NFR-01, NFR-03 |
| FR-04 | Proposed initial requirement FR-04 | NFR-01, NFR-02 |
| FR-05 | Proposed initial requirement FR-05 | NFR-01, NFR-02, NFR-04 |
| FR-06 | Proposed initial requirement FR-06 | NFR-01, NFR-04 |
