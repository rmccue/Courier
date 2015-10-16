# Courier Syntax

Courier supports two different syntaxes. These syntaxes are passed into the
`\Courier\plugin_requires` function as arguments:

1. Basic syntax:

   ```php
   \Courier\plugin_requires( string $dep [, string $... ] );
   ```

2. Advanced syntax:

   ```php
   \Courier\plugin_requires( array|object $config );
   ```

   Where `$config` is an array with the keys (or object with properties):

   * `require`
   * `require-dev`
   * `provides`

## Basic Syntax
The basic syntax is the simplest format, which allows specifying plugin
dependencies in a clear, unambiguous manner. However, it does not allow
specifying other configuration, such as `require-dev` and `provides`.

The basic syntax takes a variable number of parameters to the
`\Courier\plugin_requires` function. Each parameter must be a string in one of the
following formats:

* `$plugin`: Specifies that the current plugin requires any version of `$plugin`
  (a plugin identifier)
* `$plugin . $constraint`: Specifies that the current plugin requires a specific
  version of `$plugin` (a plugin identifier). `$constraint` follows the version
  requirement format below.

  > **Note:** Exact version constraints **must** specify the version with a
  > preceding `=` operator to disambiguate the plugin identifier from the
  > version requirement.


## Plugin and Version Requirements
### Plugin Identifiers
Plugin identifiers can be specified in one of two syntaxes:

* `json-rest-api` (simple ID syntax): This is a WordPress.org plugin repository
  ID. This directly maps to a plugin available at
  `http://wordpress.org/plugins/<id>/`

* `rarst/laps` (Composer syntax): This is a Composer package. This is matched
  according to Composer semantics, which typically maps to a package available
  on [Packagist](http://packagist.org/).

Composer packages are handled as libraries by default, however if specified with
a `type` of `wordpress-plugin` they are installed as WordPress plugins. Plugins
specified using the simple ID syntax are always treated as WordPress plugins.

> **Implementation note:** Libraries are loaded into the `mu-plugins` directory,
> and are registered by Courier, using the autoload semantics as per a typical
> Composer installation.
>
> Plugins are loaded into the `plugins` directory, and must follow native
> WordPress plugin loading (such as plugin headers). Autoloaders will still
> be registered.

### Version Constraints
Version constraints follow the same matching semantics as Composer:

Exact version: `1.0.2` (requires `=` operator for basic syntax)
: Requires exactly version `1.0.2` of the plugin.

  If you're using the basic syntax, you **must** specify `=` as the operator to
  disambiguate the plugin name from the version.

Range: `>=1.0` `>=1.0,<2.0` `>=1.0,<1.1 | >=1.2`
: Requires the plugin to be within a certain range of valid versions.

  Valid operators are `>`, `>=`, `<`, `<=`, `!=`.

  You can define multiple ranges, separated by a comma, which will be treated as
  a **logical AND**. A pipe symbol `|` will be treated as a **logical OR**.
  AND has higher precedence than OR.

Wildcard: `1.0.*`
: Requires the plugin to be within a certain range of versions, specified using
  a `*` wildcard.

  `1.0.*` is the equivalent of `>=1.0,<1.1`.

Tilde operator: `~1.2`
: Requires the plugin to be within a certain range of versions, specified using
  the `~` operator. Useful for projects that follow semantic versioning.

  `~1.2` is equivalent to `>=1.2,<2.0`.

  The `~` operator is best explained by example: `~1.2` is equivalent to
  `>=1.2,<2.0`, while `~1.2.3` is equivalent to `>=1.2.3,<1.3`. As you can see
  it is mostly useful for projects respecting [semantic
  versioning](http://semver.org/). A common usage would be to mark the minimum
  minor version you depend on, like `~1.2` (which allows anything up to, but not
  including, 2.0). Since in theory there should be no backwards compatibility
  breaks until 2.0, that works well. Another way of looking at it is that using
  `~` specifies a minimum version, but allows the last digit specified to go up.

  > **Note:** Though `2.0-beta.1` is strictly before `2.0`, a version constraint
  > like `~1.2` would not install it. As said above `~1.2` only means the `.2`
  > can change but the `1.` part is fixed.


## Advanced Syntax
The advanced syntax allows specifying all available options for Courier. The
syntax is inspired by Composer, and can be made compatible for
non-Courier sites.

The advanced syntax takes a single parameter to the `\Courier\plugin_requires`
function. This parameter is either an associative array, or an object that can
be cast to an array (via `get_object_vars`). The data contains the following
key-value pairs (all except `require` are optional):

`require` (required)
: Requirements for the current plugin.

  This value is an associative array that maps **plugin identifiers**
  (e.g. `json-rest-api`) to **plugin versions** (e.g. `~1.1`). See above for the
  format for identifier and version requirements.

`require-dev`
: Requirements for the current plugin in development mode. This value follows
  the same format as `require`. These plugins are specified as required only
  if `WP_DEBUG` is enabled on the current site.

`conflict`
: Plugins which conflict with the current plugin, and cannot be enabled at the
  same time.

  Note that when specifying ranges like `<1.0, >= 1.1` in a `conflict` link,
  this will state a conflict with all versions that are less than 1.0 *and*
  equal or newer than 1.1 at the same time, which is probably not what you want.
  You probably want to go for `<1.0 | >= 1.1` in this case.

`replace`
: Plugins which are replaced by the current plugin.

  This allows you to fork a package, publish it under a different name with its
  own version numbers, while packages requiring the original package continue to
  work with your fork because it replaces the original package.

`provide`
: Plugins which are "provided" by this package.

  This is mostly useful for common interfaces and library-style plugins. A
  plugin could depend on some virtual `logger` package, any library that
  implements this logger interface would simply list it in `provide`.
