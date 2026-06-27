import { Card, CardContent } from './ui/card'
import { Link } from './Link'
import type { ActionItem } from '../schemas/actionCenter'

/** One Action Center card: what / why / deadline / who / link / blocker. */
export function ActionCard({ item }: { item: ActionItem }) {
  return (
    <Card>
      <CardContent className="grid gap-1 py-4">
        <p className="font-medium"><bdi>{item.what}</bdi></p>
        <p className="text-sm text-muted-foreground"><bdi>{item.why}</bdi></p>
        <dl className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          {item.deadline ? <div><dt className="inline font-medium">Due: </dt><dd className="inline">{item.deadline}</dd></div> : null}
          {item.who ? <div><dt className="inline font-medium">Owner: </dt><dd className="inline"><bdi>{item.who}</bdi></dd></div> : null}
          {item.blocker ? <div><dt className="inline font-medium">Blocked by: </dt><dd className="inline"><bdi>{item.blocker}</bdi></dd></div> : null}
        </dl>
        {item.href ? <Link href={item.href} className="text-sm">Open</Link> : null}
      </CardContent>
    </Card>
  )
}
