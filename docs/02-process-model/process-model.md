# Process Model

## Selected process model

The project will use an **iterative and incremental process with Scrum-inspired practices**. Work will be organized into three-week iterations, each aiming to produce a small, reviewable outcome. When software is available, that outcome should be a tested improvement to the learning management system.

This is an adaptation rather than a claim that the project follows the complete Scrum framework. The project is carried out by one contributor, who handles planning, development, testing, and documentation. A prioritized backlog, iteration planning, reviews, and retrospectives provide structure without requiring separate Scrum roles.

## Process model justification

The project is at an early stage: its problem, user workflows, and detailed requirements still need confirmation. An iterative approach lets the contributor gather feedback from students and teachers and revise the backlog before committing to a broad implementation.

Building the system incrementally also makes the proposed LMS features easier to demonstrate and review. For example, course materials can be implemented and checked before moving on to assignments and submissions. This gives stakeholders opportunities to identify missing needs early and provides clear acceptance criteria for each increment.

The three-week iteration length provides a predictable checkpoint to plan work, assess progress, and reflect; it is not a role-rotation schedule. A fully sequential process such as Waterfall would make it harder to respond to changing requirements, while adopting every Scrum ceremony would add unnecessary process overhead for a solo project. The selected approach keeps regular feedback and planning without requiring those formalities.

## Iteration cycle

Each three-week iteration follows this cycle:

1. **Plan:** Agree on an iteration goal, select the highest-priority ready items from the product backlog, and confirm their acceptance criteria.
2. **Design and build:** Implement the selected work in small, reviewable changes. Keep the design and requirements documentation in step with the implementation.
3. **Test continuously:** Check each feature against its acceptance criteria, run relevant regression tests, and fix issues before presenting the work.
4. **Review:** Demonstrate the working increment, gather feedback, and record new or changed requirements in the backlog.
5. **Reflect:** Discuss what went well and what should improve in the next iteration.

The contributor should integrate work regularly rather than waiting until the end of an iteration. If an item cannot be completed, record its status and next steps, then reprioritize it during planning.

## Requirements management

The product backlog is the single prioritized list of proposed features, fixes, and research tasks. Requirements should be written so the contributor and stakeholders can understand and verify them.

Use this user-story format where it fits:

> As a **[student or teacher]**, I want **[goal]**, so that **[benefit]**.

Each feature requirement should include:

- A clear description of the user need and intended outcome.
- A priority agreed with stakeholders.
- Testable acceptance criteria, including relevant error or edge cases.
- Any known dependencies, assumptions, and related requirements.

Before work is selected for an iteration, the contributor should understand the requirement, confirm its acceptance criteria with stakeholders where needed, and identify any major dependency. When stakeholder feedback changes a requirement, update the backlog and relevant documentation.

## Responsibility areas

The responsibilities below are handled by the single project contributor; they are not separate people or approval gates:

- **Project manager:** Coordinates iteration planning, tracks progress and blockers, and helps arrange stakeholder feedback.
- **Lead developer:** Guides technical design, implementation, and code integration.
- **Tester:** Plans and performs checks against acceptance criteria and reports defects.
- **Documentation lead:** Keeps requirements, process notes, and supporting diagrams up to date.

If additional contributors join, assign these responsibilities and agree on handoffs at that time. The project contributor remains responsible for product quality in the current solo arrangement.

## Completion criteria

An item is complete when:

- Its agreed acceptance criteria pass.
- Relevant tests have been run and identified failures have been addressed or recorded.
- The change has been reviewed and integrated with the rest of the project.
- User-facing or project documentation affected by the change has been updated.

An iteration should end with a working increment that the contributor can demonstrate for stakeholder review. If the project does not yet have runnable software, the iteration outcome should be made explicit—for example, a refined requirements set or a reviewed design—and not described as a working software increment.

## Quality considerations

As requirements are gathered, the contributor should agree with stakeholders on measurable expectations for usability, accessibility, privacy, security, and reliability. These expectations should be recorded as testable requirements rather than assumed. In particular, access to course materials and student submissions should be considered when defining user roles and permissions.
