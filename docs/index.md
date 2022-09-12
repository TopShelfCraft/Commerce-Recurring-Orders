# Recurring Orders

The Recurring Orders plugin adds a layer of extra functionality for processing repeating Orders in Craft Commerce,
according to a scheduled recurrence interval.

This begets two new concepts:

 - Recurring Order
 - Generated Orders

Any order with a Recurrence Status is considered a "Recurring Order" (i.e. an order managed by the Recurring Orders plugin).



Under the hood, each Recurring Order is just a normal Commerce Order element, with some additional
attributes and methods.



 - Recurrence Status
 - Recurrence Interval
 - Last Recurrence
 - Next Recurrence
 - Date Marked Imminent
 - Payment Source ID
 - Error Count
 - Error Reason
 - Retry Date


