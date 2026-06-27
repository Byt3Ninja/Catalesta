import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Banner } from '../components/Banner'
import { Button } from '../components/Button'
import { Field } from '../components/Field'
import { FormLayout } from '../components/FormLayout'
import { Link } from '../components/Link'
import { createCohort, updateCohort, openCohort } from '../api/cohorts'
import type { Cohort } from '../schemas/cohorts'

const STEPS = ['Create', 'Attach form', 'Attach stages', 'Dates', 'Review', 'Open'] as const

function toIso(date: string): string | null {
  return date ? `${date}T00:00:00+00:00` : null
}

export function CohortSetupWizard({ programId }: { programId: string }) {
  const [step, setStep] = useState(0)
  const [name, setName] = useState('')
  const [opens, setOpens] = useState('')
  const [closes, setCloses] = useState('')
  const [cohort, setCohort] = useState<Cohort | null>(null)
  const [opened, setOpened] = useState(false)

  const createMutation = useMutation({
    mutationFn: () => createCohort(programId, { name: name.trim() }),
    onSuccess: (c) => { setCohort(c); setStep(1) },
  })
  const datesMutation = useMutation({
    mutationFn: () => {
      if (!cohort) return Promise.reject(new Error('No cohort to update'))
      return updateCohort(cohort.id, { enrollment_opens_at: toIso(opens), enrollment_closes_at: toIso(closes) })
    },
    onSuccess: () => setStep(4),
  })
  const openMutation = useMutation({
    mutationFn: () => {
      if (!cohort) return Promise.reject(new Error('No cohort to open'))
      return openCohort(cohort.id)
    },
    onSuccess: () => setOpened(true),
  })

  const rail = (
    <nav aria-label="Sections" className="grid gap-1 text-sm">
      <Link href="/programs">Programs</Link>
    </nav>
  )

  return (
    <AppShell
      rail={rail}
      pageHeader={<h1 id="wizard-heading" className="text-2xl font-semibold">Set up cohort</h1>}
    >
      <section aria-labelledby="wizard-heading" className="grid max-w-2xl gap-6">
        <ol className="flex flex-wrap gap-2 text-sm" aria-label="Setup steps">
          {STEPS.map((label, i) => (
            <li
              key={label}
              aria-current={i === step ? 'step' : undefined}
              className={
                i === step
                  ? 'rounded-full bg-primary px-3 py-1 font-medium text-primary-foreground'
                  : 'rounded-full bg-secondary px-3 py-1 text-secondary-foreground'
              }
            >
              {i + 1}. {label}
            </li>
          ))}
        </ol>

        {opened ? (
          <Banner variant="success">The cohort is open for intake. <Link href={`/cohorts/${cohort!.id}`}>View cohort</Link></Banner>
        ) : step === 0 ? (
          <form
            onSubmit={(e) => { e.preventDefault(); if (name.trim()) createMutation.mutate() }}
            noValidate
            className="grid gap-4 rounded-lg border border-border p-4"
          >
            <h2 className="text-lg font-medium">Create</h2>
            {createMutation.isError && <Banner variant="error">Could not create the cohort. Try again.</Banner>}
            <FormLayout>
              <Field label="Cohort name" name="cohort-name" required value={name} onChange={(e) => setName(e.target.value)} />
            </FormLayout>
            <div><Button type="submit" loading={createMutation.isPending} disabled={!name.trim()}>Create &amp; continue</Button></div>
          </form>
        ) : step === 1 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Attach form</h2>
            <p className="text-sm text-muted-foreground">
              You can bind a published application form from the program configuration hub once forms are available. Skip
              for now and attach it later.
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(2)}>Skip for now</Button>
            </div>
          </div>
        ) : step === 2 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Attach stages</h2>
            <p className="text-sm text-muted-foreground">
              You can attach a stage pipeline from the configuration hub once stages are available. Skip for now and
              configure it later.
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(3)}>Skip for now</Button>
            </div>
          </div>
        ) : step === 3 ? (
          <form
            onSubmit={(e) => { e.preventDefault(); datesMutation.mutate() }}
            noValidate
            className="grid gap-4 rounded-lg border border-border p-4"
          >
            <h2 className="text-lg font-medium">Dates</h2>
            {datesMutation.isError && <Banner variant="error">Could not save the dates. Try again.</Banner>}
            <FormLayout>
              <Field label="Opens" name="opens" type="date" value={opens} onChange={(e) => setOpens(e.target.value)} />
              <Field label="Closes" name="closes" type="date" value={closes} onChange={(e) => setCloses(e.target.value)} />
            </FormLayout>
            <div><Button type="submit" loading={datesMutation.isPending}>Continue</Button></div>
          </form>
        ) : step === 4 ? (
          <div className="grid gap-4 rounded-lg border border-border p-4">
            <h2 className="text-lg font-medium">Review</h2>
            <dl className="grid gap-2 text-sm">
              <div className="flex justify-between"><dt className="text-muted-foreground">Name</dt><dd><bdi>{name}</bdi></dd></div>
              <div className="flex justify-between"><dt className="text-muted-foreground">Window</dt><dd>{opens || '—'} → {closes || '—'}</dd></div>
            </dl>
            {openMutation.isError && <Banner variant="error">Could not open the cohort. Try again.</Banner>}
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setStep(3)}>Back</Button>
              <Button loading={openMutation.isPending} onClick={() => openMutation.mutate()}>Open cohort</Button>
            </div>
          </div>
        ) : null}
      </section>
    </AppShell>
  )
}
