# Updating Orchestrations After Migrating Legacy Transformations

This document outlines the plan to update Orchestrations (also referred to as Flows) once the Legacy Transformations have been migrated to the new transformations.

---

## Plan Outline

1. **Identify Orchestrations/Flows That Use the Old Configurations**
   - After migration, we have a list of old configuration IDs (`oldConfigIds`) and their corresponding new IDs (`newConfigIds`). We need to locate any Orchestration (or Flow) that references these old IDs in its tasks.

2. **Fetch Orchestrations from the Orchestrator API**
   - Use [keboola/orchestrator-php-client](https://github.com/keboola/orchestrator-php-client) or equivalent Orchestrator API calls to:
     1. List all orchestrations in the project.  
     2. Fetch tasks for each orchestration.

3. **Find Tasks Referencing Old Config IDs**
   - Parse the tasks to find references to `componentId="transformation"` plus a matching `configurationId` in `oldConfigIds`.
   - Maintain a mapping of `oldConfigId` → `newConfigId` to perform the replacements.

4. **Update the Task with the New Config ID**
   - For each task referencing an `oldConfigId`, replace that `configurationId` with the corresponding `newConfigId`.
   - Ensure you preserve other task settings (e.g., phase, active/inactive status, etc.).

5. **Save the Updated Orchestrations**
   - Use the Orchestrator client to update or save changes.
   - Confirm that the tasks now reference the correct `newConfigId`.

6. **Error Handling & Rollback**
   - Decide how to handle partial failures if an Orchestrator update fails.
   - Potentially revert the whole migration or just warn the user that some orchestrations could not be updated.

7. **Validate the Final State**
   - Optionally, list orchestrations again to confirm none of the tasks still reference old configuration IDs.

8. **User Notification/Logging**
   - Provide logs showing which orchestrations were updated and which tasks replaced the old config IDs with the new IDs.

---

## Additional Considerations

- **One Old Config → Multiple New Configs**  
  Some legacy transformations might be split across multiple new configurations, requiring creation of multiple tasks for the new configs.

- **Multiple Old Configs → One New Config**  
  Check if multiple old transformations are merged into a single new config. If so, orchestrations referencing each old ID can be redirected to the same new config ID.

- **Phases & Execution Order**  
  Orchestrations rely on phases to manage parallel and sequential tasks. If transformations are split or combined, ensure that the task order or phases still work as intended.

- **Performance**  
  Listing all orchestrations could be slow in large projects; be mindful of efficiency.

- **Testing**  
  Test with small sets of orchestrations referencing a few transformations. Confirm the final UI and job runs properly with new config references.

---

## Confirming Requirements

Before implementing, clarify:

1. **Mapping Strategy**:  
   Is it always 1-to-1, or can it be 1-to-many or many-to-1 between old and new configurations?

2. **Client API Approach**:  
   Does [keboola/orchestrator-php-client](https://github.com/keboola/orchestrator-php-client) let us do partial updates? Or do we need to recreate tasks entirely?

3. **Rollback Strategy**:  
   What is the expected behavior if updating tasks fails?

4. **Edge Cases**:  
   - Orchestrations referencing transformations that were never migrated (ignore or throw an error)?  
   - Orchestrations referencing a different transformation component?

---

_Use this plan to guide implementation of Orchestration updates as part of the Legacy to V2 Migration process._
