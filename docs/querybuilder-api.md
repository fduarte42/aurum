# QueryBuilder API Reference

The QueryBuilder provides a fluent interface for building complex SQL queries with automatic join resolution for all relationship types, including Many-to-Many relationships.

## Basic Methods

### `select(string ...$columns): self`

Specify columns to select in the query.

```php
$qb->select('u.name', 'u.email');
$qb->select('u', 'r');  // Select all columns from both entities
```

### `from(string $table, string $alias): self`

Set the main table/entity for the query. Automatically detects entity classes and sets up metadata for join resolution.

```php
$qb->from(User::class, 'u');
$qb->from('users', 'u');  // Direct table name
```

### `where(string $condition): self`

Add WHERE conditions to the query.

```php
$qb->where('u.active = :active');
$qb->where('u.name LIKE :pattern');
```

### `setParameter(string $name, mixed $value): self`

Bind parameters to the query for security.

```php
$qb->setParameter('active', true);
$qb->setParameter('pattern', '%admin%');
```

## Join Methods

### `innerJoin(string $join, string $alias, ?string $condition = null): self`

Create an INNER JOIN. When joining entity relationships, join conditions are automatically resolved.

```php
// Automatic join condition resolution
$qb->innerJoin('u.roles', 'r');        // Many-to-Many (automatic junction table)
$qb->innerJoin('u.profile', 'p');      // OneToOne
$qb->innerJoin('u.posts', 'posts');    // OneToMany

// Manual join condition
$qb->innerJoin('roles', 'r', 'u.role_id = r.id');
```

### `leftJoin(string $join, string $alias, ?string $condition = null): self`

Create a LEFT JOIN with automatic join condition resolution.

```php
$qb->leftJoin('u.roles', 'r');         // Optional Many-to-Many relationship
$qb->leftJoin('u.posts', 'p');         // Optional OneToMany relationship
```

### `rightJoin(string $join, string $alias, ?string $condition = null): self`

Create a RIGHT JOIN with automatic join condition resolution.

```php
$qb->rightJoin('u.roles', 'r');
```

## Many-to-Many Automatic Join Resolution

The QueryBuilder automatically handles Many-to-Many relationships by generating the necessary junction table joins.

### Owning Side Queries

```php
// User entity with roles relationship
$qb->from(User::class, 'u')
   ->innerJoin('u.roles', 'r');

// Generated SQL:
// FROM users u 
// INNER JOIN user_roles jt_12345 ON u.id = jt_12345.user_id
// INNER JOIN roles r ON jt_12345.role_id = r.id
```

### Inverse Side Queries

```php
// Role entity with users relationship (mappedBy: 'roles')
$qb->from(Role::class, 'r')
   ->innerJoin('r.users', 'u');

// Generated SQL:
// FROM roles r
// INNER JOIN user_roles jt_67890 ON r.id = jt_67890.role_id  
// INNER JOIN users u ON jt_67890.user_id = u.id
```

### Custom JoinTable Support

The QueryBuilder respects custom `#[JoinTable]` configurations:

```php
#[ManyToMany(targetEntity: Role::class)]
#[JoinTable(
    name: 'custom_user_roles',
    joinColumns: [new JoinColumn(name: 'user_uuid', referencedColumnName: 'id')],
    inverseJoinColumns: [new JoinColumn(name: 'role_uuid', referencedColumnName: 'id')]
)]
private array $roles = [];

// QueryBuilder automatically uses custom table and column names
$qb->from(User::class, 'u')->innerJoin('u.roles', 'r');
// Uses: custom_user_roles table with user_uuid and role_uuid columns
```

## Query Execution Methods

### `getResult(): \PDOStatement`

Execute the query and return a PDOStatement iterator for efficient iteration over database results without loading all records into memory at once. The fetch mode is automatically set to `PDO::FETCH_ASSOC`.

```php
$statement = $qb->from(User::class, 'u')
                ->innerJoin('u.roles', 'r')
                ->where('r.name = :role')
                ->setParameter('role', 'admin')
                ->getResult();

// Iterate efficiently over results (fetch mode already set to ASSOC)
foreach ($statement as $row) {
    // Process each row without loading all into memory
    echo "User: {$row['name']}\n";
}
```

### `getOneOrNullResult(): ?object`

Execute the query and return a single result or null.

```php
$user = $qb->from(User::class, 'u')
           ->where('u.email = :email')
           ->setParameter('email', 'admin@example.com')
           ->getOneOrNullResult();
```

### `getSingleScalarResult(): mixed`

Execute the query and return a single scalar value.

```php
$count = $qb->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->innerJoin('u.roles', 'r')
            ->where('r.name = :role')
            ->setParameter('role', 'admin')
            ->getSingleScalarResult();
```

### `getSQL(): string`

Get the generated SQL query string (useful for debugging).

```php
$sql = $qb->from(User::class, 'u')
          ->innerJoin('u.roles', 'r')
          ->getSQL();

echo $sql;
// Output: SELECT u FROM users u INNER JOIN user_roles jt_12345 ON u.id = jt_12345.user_id INNER JOIN roles r ON jt_12345.role_id = r.id
```

## Advanced Features

### Subqueries

```php
$subquery = $qb->createSubquery()
               ->select('COUNT(p.id)')
               ->from(Post::class, 'p')
               ->where('p.user_id = u.id');

$qb->select('u')
   ->from(User::class, 'u')
   ->where("({$subquery->getSQL()}) > :minPosts")
   ->setParameter('minPosts', 5);
```

### Complex Many-to-Many Queries

```php
// Find users with multiple specific roles
$qb->select('u')
   ->from(User::class, 'u')
   ->innerJoin('u.roles', 'r1')
   ->innerJoin('u.roles', 'r2')  // Multiple joins to same relationship
   ->where('r1.name = :role1')
   ->andWhere('r2.name = :role2')
   ->setParameter('role1', 'admin')
   ->setParameter('role2', 'editor');
```

### Performance Optimization

```php
// Efficient column selection for Many-to-Many queries
$qb->select('u.name', 'r.name')  // Only needed columns
   ->from(User::class, 'u')
   ->innerJoin('u.roles', 'r')
   ->where('u.active = :active')  // Filter main table first
   ->andWhere('r.name IN (:roles)')  // Then filter joined table
   ->setParameter('active', true)
   ->setParameter('roles', ['admin', 'editor']);
```

## Error Handling

The QueryBuilder throws `ORMException` for various error conditions:

```php
try {
    $result = $qb->from(User::class, 'u')
                 ->innerJoin('u.invalidProperty', 'x')  // Invalid property
                 ->getResult();
} catch (ORMException $e) {
    // Handle: "Cannot resolve join condition for property 'invalidProperty'"
}
```

## Best Practices

1. **Use automatic join resolution** for entity relationships instead of manual conditions
2. **Filter early** - add WHERE conditions on the main entity before joined entities
3. **Select specific columns** when possible to reduce data transfer
4. **Use parameter binding** for all dynamic values to prevent SQL injection
5. **Consider LEFT JOIN** for optional Many-to-Many relationships
6. **Use EXISTS subqueries** for existence checks instead of joins when you don't need the related data

## Integration with Entity Manager

```php
// Create QueryBuilder from EntityManager
$qb = $entityManager->createQueryBuilder('u');

// Or from Repository
$userRepository = $entityManager->getRepository(User::class);
$qb = $userRepository->createQueryBuilder('u');
```

The QueryBuilder is fully integrated with Aurum's metadata system, providing seamless automatic join resolution for all relationship types including complex Many-to-Many relationships.
