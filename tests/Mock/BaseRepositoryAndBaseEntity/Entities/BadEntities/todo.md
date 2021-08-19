Create test entities with errors:

 - Missing `#[TableName]` attribute ✅
 - Missing primary key ✅
 - Non-nullable primary key type (?int) ✅
 - Primary key without `null` as default value ✅
 - Multiple primary keys defined. ✅

 - `#[FetchArray]` attribute pointing to class, that has no foreign
key pointing back to us ✅

Create test repositories with errors:
    
 - Wrong order of entities  ✅
