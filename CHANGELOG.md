# 2.1.3
##  2024-11-22

1. [](#improvement)
    * removing all objects that were not part of the directus response while syncing
    * the name of filed that holds information of last modification date is now configurable


# 2.1.2
##  2024-10-14

1. [](#improvement)
    * removed client specific dead code

# 2.1.1
##  2024-10-08

1. [](#new)
    * extended flex collection to get directory config information

# 2.1.0
##  2024-10-01

1. [](#new)
    * endpoint to remove chached assets by file ID
    * `directusFileInfo()` for receiving file infos by file UUID

1. [](#improvement)
    * Reducing memory usage
    * `directusFile()` now also accepts the file ID (queries and caches file info)

1. [](#bugfix)
    * Date comparison for news used the wrong format

# 2.0.9
##  2024-08-28

1. [](#bugfix)
    * bugfix for new User Agent check

# 2.0.8
##  2024-08-27

1. [](#new)
    * added a config setting to deliver the maintenance page with a different http code for specific User Agents

# 2.0.7
##  2024-06-26

1. [](#new)
    * added events to hook into for extension plugins

# 2.0.6
##  2024-06-05

1. [](#improvement)
    * added Flex Collection function for getting entries by field&value or field that has values at all

# 2.0.5
##  2024-06-04

1. [](#improvement)
    * improved backup and restore mechanism

# 2.0.4
##  2024-04-23

1. [](#improvement)
    * querying from directus with default status conditions (configurable by env), replacing older override logic

1. [](#new)
    * publicNews FlexCollection filter for news filtering

# 2.0.3
##  2024-04-10

1. [](#improvement)
    * custom recursive move function because rename() is unreliable

# 2.0.2
##  2024-04-09


1. [](#improvement)
    * Tested with real directus and enhanced create and update functionality

1. [](#new)
    * Maintenance template/output on locked

# 2.0.1
##  2024-03-12

1. [](#bugfix)
    * Bugfixes

# 2.0.0
##  2024-03-12

1. [](#new)
    * Feature complete for now
