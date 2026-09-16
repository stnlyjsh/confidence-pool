export function defaultSeasonYear(): number {
  const now = new Date()
  // The NFL season starting in September of year Y is labeled "Y" all the
  // way through its Jan/Feb finish, matching ESPN's own season.year field.
  return now.getMonth() >= 7 ? now.getFullYear() : now.getFullYear() - 1
}
